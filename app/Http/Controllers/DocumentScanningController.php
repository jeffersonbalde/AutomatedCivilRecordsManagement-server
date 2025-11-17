<?php
// app/Http/Controllers/DocumentScanningController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\DocumentRecord;
use App\Models\Admin;
use App\Models\Staff;

class DocumentScanningController extends Controller
{
    public function uploadDocument(Request $request)
    {
        Log::info('=== UPLOAD DEBUG START ===');

        try {
            $validator = Validator::make($request->all(), [
                'document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
                'record_type' => 'required|in:birth,marriage,death',
                'original_filename' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all())
                ], 422);
            }

            $file = $request->file('document');
            $recordType = $request->record_type;
            $originalFilename = $request->original_filename;

            // Determine who is uploading (admin or staff)
            $uploaderId = auth()->id();
            $uploaderType = null;
            $uploaderName = 'System';

            // Check if current user is an admin
            if (auth()->guard('admin')->check()) {
                $uploaderType = 'App\\Models\\Admin';
                $uploader = Admin::find($uploaderId);
                $uploaderName = $uploader->full_name ?? $uploader->username ?? 'Admin';
                Log::info('Uploader is admin', ['id' => $uploaderId, 'name' => $uploaderName]);
            }
            // Check if current user is staff
            elseif (auth()->guard('staff')->check()) {
                $uploaderType = 'App\\Models\\Staff';
                $uploader = Staff::find($uploaderId);
                $uploaderName = $uploader->full_name ?? $uploader->email ?? 'Staff';
                Log::info('Uploader is staff', ['id' => $uploaderId, 'name' => $uploaderName]);
            } else {
                Log::error('No authenticated user found (not admin or staff)');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Verify uploader exists
            if (!$uploader) {
                Log::error('Uploader not found in database', [
                    'id' => $uploaderId,
                    'type' => $uploaderType
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'User account not found'
                ], 401);
            }

            Log::info('Uploader verified', [
                'id' => $uploaderId,
                'type' => $uploaderType,
                'name' => $uploaderName
            ]);

            // Generate unique filename
            $fileExtension = $file->getClientOriginalExtension();
            $storedFilename = Str::uuid() . '.' . $fileExtension;
            $filePath = "documents/{$recordType}/" . $storedFilename;

            // Create directory if it doesn't exist
            $directory = "documents/{$recordType}";
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Store file
            $stored = Storage::disk('public')->put($filePath, file_get_contents($file));

            if (!$stored) {
                throw new \Exception('Failed to store file');
            }

            // Extract basic text for search
            $extractedText = $this->extractSearchableText($originalFilename, $recordType);

            // Save to database
            $documentRecord = DocumentRecord::create([
                'record_type' => $recordType,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $filePath,
                'file_url' => url("/api/document-scanning/file/0"), // Temporary, will update
                'extracted_text' => $extractedText,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $uploaderId,
                'uploader_type' => $uploaderType,
                'is_active' => true
            ]);

            // Update with correct file URL
            $documentRecord->update([
                'file_url' => url("/api/document-scanning/file/{$documentRecord->id}")
            ]);

            Log::info('Document record created successfully', ['id' => $documentRecord->id]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully!',
                'data' => [
                    'document_id' => $documentRecord->id,
                    'record_type' => $recordType,
                    'original_filename' => $originalFilename,
                    'file_url' => $documentRecord->file_url,
                    'uploaded_at' => now()->toDateTimeString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Upload error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractSearchableText($filename, $recordType)
    {
        $cleanName = pathinfo($filename, PATHINFO_FILENAME);
        $searchableText = strtolower($cleanName . ' ' . $recordType);
        return $searchableText;
    }

    public function destroy($id)
    {
        try {
            Log::info('Attempting to delete document', ['document_id' => $id]);

            $document = DocumentRecord::find($id);

            if (!$document) {
                Log::warning('Document not found for deletion', ['document_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found.'
                ], 404);
            }

            // Delete the physical file from storage
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
                Log::info('Physical file deleted', ['file_path' => $document->file_path]);
            }

            // Soft delete the record (if using soft deletes)
            // Or hard delete if not using soft deletes
            $document->delete();

            Log::info('Document deleted successfully', ['document_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully!'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete document error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: ' . $e->getMessage()
            ], 500);
        }
    }

    public function searchDocuments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'nullable|string',
                'record_type' => 'nullable|in:birth,marriage,death,all'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid search parameters'
                ], 422);
            }

            $query = $request->input('query', '');
            $recordType = $request->input('record_type', 'all');

            $documentsQuery = DocumentRecord::where('is_active', true)
                ->when($recordType !== 'all', function ($q) use ($recordType) {
                    return $q->where('record_type', $recordType);
                })
                ->when(!empty(trim($query)), function ($q) use ($query) {
                    return $q->where(function ($q) use ($query) {
                        $q->where('original_filename', 'LIKE', "%{$query}%")
                            ->orWhere('extracted_text', 'LIKE', "%{$query}%")
                            ->orWhere('record_type', 'LIKE', "%{$query}%");
                    });
                })
                ->orderBy('created_at', 'desc');

            $results = $documentsQuery->get()->map(function ($document) {
                return [
                    'id' => $document->id,
                    'record_type' => $document->record_type,
                    'original_filename' => $document->original_filename,
                    'file_url' => url("/api/document-scanning/file/{$document->id}"),
                    'file_size' => $this->formatFileSize($document->file_size),
                    'uploaded_at' => $document->created_at->format('M d, Y H:i'),
                    'uploaded_by' => $document->uploader_name,
                    'is_image' => strpos($document->mime_type, 'image') !== false,
                    'is_pdf' => $document->mime_type === 'application/pdf'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'total' => $results->count(),
                    'query' => $query
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Document search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed. Please try again.'
            ], 500);
        }
    }

    public function getDocument($id)
    {
        try {
            $document = DocumentRecord::where('id', $id)
                ->where('is_active', true)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $document->id,
                    'record_type' => $document->record_type,
                    'original_filename' => $document->original_filename,
                    'file_url' => url("/api/document-scanning/file/{$document->id}"),
                    'file_size' => $this->formatFileSize($document->file_size),
                    'uploaded_at' => $document->created_at->format('M d, Y H:i'),
                    'uploaded_by' => $document->uploader_name,
                    'is_image' => strpos($document->mime_type, 'image') !== false,
                    'is_pdf' => $document->mime_type === 'application/pdf'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get document error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Document not found.'
            ], 404);
        }
    }

    public function getAllDocuments(Request $request)
    {
        try {
            $documents = DocumentRecord::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'record_type' => $document->record_type,
                        'original_filename' => $document->original_filename,
                        'file_url' => url("/api/document-scanning/file/{$document->id}"),
                        'file_size' => $document->file_size, // Send raw bytes, not formatted
                        'file_size_formatted' => $this->formatFileSize($document->file_size), // Optional: send formatted version too
                        'uploaded_at' => $document->created_at->format('M d, Y H:i'),
                        'uploaded_by' => $document->uploader_name,
                        'is_image' => strpos($document->mime_type, 'image') !== false,
                        'is_pdf' => $document->mime_type === 'application/pdf',
                        'created_at' => $document->created_at,
                        'updated_at' => $document->updated_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $documents,
                'total' => $documents->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Get all documents error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents. Please try again.'
            ], 500);
        }
    }
    public function serveFile($id)
    {
        try {
            $document = DocumentRecord::findOrFail($id);

            if (!Storage::disk('public')->exists($document->file_path)) {
                abort(404, 'File not found');
            }

            $file = Storage::disk('public')->get($document->file_path);
            $mimeType = Storage::disk('public')->mimeType($document->file_path);

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $document->original_filename . '"')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            Log::error('File serve error: ' . $e->getMessage());
            abort(404, 'File not found');
        }
    }

    private function formatFileSize($bytes)
    {
        if ($bytes == 0) return '0 Bytes';

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}

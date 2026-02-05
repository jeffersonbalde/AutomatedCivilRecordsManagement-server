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
use Smalot\PdfParser\Parser as PdfParser;

class DocumentScanningController extends Controller
{
    public function uploadDocument(Request $request)
    {
        Log::info('=== UPLOAD DEBUG START ===');

        try {
            $validator = Validator::make($request->all(), [
                'document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
                'record_type' => 'required|in:birth,marriage,death',
                'original_filename' => ['required', 'string', 'max:255']
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

            // Phase 1: Document naming convention - filename must include record type and details
            if (!$this->isValidDocumentFilename($originalFilename, $recordType)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filename must follow the naming convention: {record_type}_{identifier}_{date}.{ext}. Example: birth_MR-001_2025-02-05.pdf or marriage_JohnDoe-Maria_2025-02-05.pdf'
                ], 422);
            }

            // Phase 1: Duplicate filename detection - same filename + record type already exists
            $duplicateExists = DocumentRecord::where('record_type', $recordType)
                ->where('original_filename', $originalFilename)
                ->where('is_active', true)
                ->exists();
            if ($duplicateExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A document with this filename already exists for this record type. Please use a different filename or delete the existing document first.'
                ], 422);
            }

            // Determine who is uploading (admin or staff)
            $uploaderId = \Illuminate\Support\Facades\Auth::id();
            $uploaderType = null;
            $uploaderName = 'System';

            // Check if current user is an admin
            if (\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
                $uploaderType = 'App\\Models\\Admin';
                $uploader = Admin::find($uploaderId);
                $uploaderName = $uploader->full_name ?? $uploader->username ?? 'Admin';
                Log::info('Uploader is admin', ['id' => $uploaderId, 'name' => $uploaderName]);
            }
            // Check if current user is staff
            elseif (\Illuminate\Support\Facades\Auth::guard('staff')->check()) {
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

            // Extract text for search (PDF full-text via smalot/pdfparser)
            $fullPath = Storage::disk('public')->path($filePath);
            $extractedText = $this->extractSearchableText($originalFilename, $recordType, $file->getMimeType(), $fullPath);

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

    /**
     * Phase 1: Check if a document with the same filename already exists (duplicate detection).
     */
    public function checkFilename(Request $request)
    {
        $recordType = $request->input('record_type');
        $originalFilename = $request->input('original_filename');
        if (!$recordType || !$originalFilename) {
            return response()->json([
                'success' => true,
                'data' => ['exists' => false],
            ]);
        }
        $exists = DocumentRecord::where('record_type', $recordType)
            ->where('original_filename', $originalFilename)
            ->where('is_active', true)
            ->exists();
        return response()->json([
            'success' => true,
            'data' => ['exists' => $exists],
        ]);
    }

    /**
     * Phase 1: Validate document filename convention.
     * Format: {record_type}_{identifier}[_{YYYY-MM-DD}].{pdf|jpg|jpeg|png}
     * Example: birth_MR-001_2025-02-05.pdf, marriage_JohnDoe-Maria.pdf
     */
    private function isValidDocumentFilename(string $filename, string $recordType): bool
    {
        $filename = trim($filename);
        if ($filename === '') {
            return false;
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            return false;
        }
        $base = pathinfo($filename, PATHINFO_FILENAME);
        if (!preg_match('/^' . preg_quote($recordType, '/') . '_[A-Za-z0-9\-_\s]+(_\d{4}-\d{2}-\d{2})?$/', $base)) {
            return false;
        }
        return true;
    }

    /**
     * Phase 2: Extract searchable text - filename + recordType; for PDFs also extract content for full-text search.
     */
    private function extractSearchableText(string $filename, string $recordType, ?string $mimeType = null, ?string $fullPath = null): string
    {
        $cleanName = pathinfo($filename, PATHINFO_FILENAME);
        $searchableText = strtolower($cleanName . ' ' . $recordType);

        if ($mimeType === 'application/pdf' && $fullPath && is_readable($fullPath)) {
            try {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($fullPath);
                $text = $pdf->getText();
                if ($text !== '') {
                    $text = preg_replace('/\s+/', ' ', trim($text));
                    $maxLen = 60000; // Leave room for DB text column
                    if (mb_strlen($text) > $maxLen) {
                        $text = mb_substr($text, 0, $maxLen);
                    }
                    $searchableText .= ' ' . strtolower($text);
                }
            } catch (\Throwable $e) {
                Log::warning('PDF text extraction failed: ' . $e->getMessage());
            }
        }

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

            // Phase 2: Support search by unique key (e.g. birth-5, marriage-12)
            $byUniqueKey = false;
            $uniqueKeyId = null;
            if (!empty(trim($query)) && preg_match('/^(birth|marriage|death)-(\d+)$/i', trim($query), $m)) {
                $byUniqueKey = true;
                $uniqueKeyId = (int) $m[2];
            }

            $documentsQuery = DocumentRecord::where('is_active', true)
                ->when($recordType !== 'all', function ($q) use ($recordType) {
                    return $q->where('record_type', $recordType);
                })
                ->when(!empty(trim($query)), function ($q) use ($query, $byUniqueKey, $uniqueKeyId) {
                    if ($byUniqueKey && $uniqueKeyId) {
                        return $q->where('id', $uniqueKeyId);
                    }
                    return $q->where(function ($q) use ($query) {
                        $q->where('original_filename', 'LIKE', "%{$query}%")
                            ->orWhere('extracted_text', 'LIKE', "%{$query}%")
                            ->orWhere('record_type', 'LIKE', "%{$query}%");
                    });
                })
                ->orderBy('created_at', 'desc');

            $queryTrimmed = trim($query);
            $results = $documentsQuery->get()->map(function ($document) use ($queryTrimmed) {
                $item = [
                    'id' => $document->id,
                    'unique_key' => $document->record_type . '-' . $document->id,
                    'record_type' => $document->record_type,
                    'original_filename' => $document->original_filename,
                    'file_url' => url("/api/document-scanning/file/{$document->id}"),
                    'file_size' => $document->file_size,
                    'file_size_formatted' => $this->formatFileSize($document->file_size),
                    'uploaded_at' => $document->created_at->format('M d, Y H:i'),
                    'uploaded_by' => $document->uploader_name,
                    'is_image' => strpos($document->mime_type, 'image') !== false,
                    'is_pdf' => $document->mime_type === 'application/pdf',
                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at
                ];
                // UX: show excerpt from PDF text where the search term appears
                if ($queryTrimmed !== '' && !empty($document->extracted_text)) {
                    $snippet = $this->buildMatchSnippet($document->extracted_text, $queryTrimmed, 100);
                    if ($snippet !== null) {
                        $item['match_snippet'] = $snippet;
                    }
                }
                return $item;
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
                    'unique_key' => $document->record_type . '-' . $document->id,
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
                        'unique_key' => $document->record_type . '-' . $document->id,
                        'record_type' => $document->record_type,
                        'original_filename' => $document->original_filename,
                        'file_url' => url("/api/document-scanning/file/{$document->id}"),
                        'file_size' => $document->file_size,
                        'file_size_formatted' => $this->formatFileSize($document->file_size),
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
            $mimeType = $document->mime_type ?? 'application/octet-stream';

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $document->original_filename . '"')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            Log::error('File serve error: ' . $e->getMessage());
            abort(404, 'File not found');
        }
    }

    /**
     * Build a short excerpt from extracted text containing the search query (for UX: show why the document matched).
     */
    private function buildMatchSnippet(string $text, string $query, int $contextChars = 100): ?string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));
        if ($text === '' || $query === '') {
            return null;
        }
        $pos = mb_stripos($text, $query);
        if ($pos === false) {
            return null;
        }
        $qlen = mb_strlen($query);
        $textLen = mb_strlen($text);
        $start = max(0, $pos - $contextChars);
        $end = min($textLen, $pos + $qlen + $contextChars);
        $snippet = mb_substr($text, $start, $end - $start);
        if ($start > 0) {
            $snippet = '…' . $snippet;
        }
        if ($end < $textLen) {
            $snippet .= '…';
        }
        return $snippet;
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

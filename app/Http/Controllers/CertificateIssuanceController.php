<?php
// app/Http/Controllers/CertificateIssuanceController.php

namespace App\Http\Controllers;

use App\Models\CertificateIssuanceLog;
use App\Models\BirthRecord;
use App\Models\MarriageRecord;
use App\Models\DeathRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CertificateIssuanceController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Certificate issuance request received:', $request->all());

        // Debug user information
        $user = Auth::user();
        Log::info('Authenticated user:', [
            'user_id' => Auth::id(),
            'user_type' => get_class($user),
            'user_exists' => !is_null($user)
        ]);

        if (!$user) {
            Log::error('No authenticated user found');
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // UPDATED VALIDATION WITH CUSTOM MESSAGES
        $validator = Validator::make($request->all(), [
            'certificate_type' => 'required|in:birth,marriage,death',
            'record_id' => 'required|integer|min:1',
            'certificate_number' => 'required|string|max:50|unique:certificate_issuance_logs,certificate_number',
            'issued_to' => 'required|string|max:255',
            'amount_paid' => 'required|numeric|min:0',
            'or_number' => 'required|string|max:50',
            'date_paid' => 'required|date',
            'purpose' => 'nullable|string|max:255',
        ], [
            'certificate_number.unique' => 'This certificate number has already been used. Please generate a new certificate.',
            'or_number.required' => 'OR number is required for accounting purposes.',
            'issued_to.required' => 'Recipient name is required.',
            'amount_paid.required' => 'Amount paid is required.',
            'amount_paid.numeric' => 'Amount must be a valid number.',
            'amount_paid.min' => 'Amount must be greater than 0.',
            'date_paid.required' => 'Date paid is required.',
            'date_paid.date' => 'Date paid must be a valid date.',
        ]);

        if ($validator->fails()) {
            Log::error('Certificate issuance validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify the record exists
            $record = $this->verifyRecordExists($request->certificate_type, $request->record_id);

            if (!$record) {
                Log::error('Record not found for certificate issuance:', [
                    'type' => $request->certificate_type,
                    'record_id' => $request->record_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found or not active'
                ], 404);
            }

            // Get user info based on actual model type
            $userType = $this->getUserType();
            $userId = $this->getUserId();

            Log::info('Creating certificate issuance log with:', [
                'user_id' => $userId,
                'user_type' => $userType
            ]);

            // Create the issuance log
            $issuanceLog = CertificateIssuanceLog::create([
                'certificate_type' => $request->certificate_type,
                'record_id' => $request->record_id,
                'certificate_number' => $request->certificate_number,
                'issued_to' => $request->issued_to,
                'amount_paid' => $request->amount_paid,
                'or_number' => $request->or_number,
                'date_paid' => $request->date_paid,
                'purpose' => $request->purpose,
                'issued_by' => $userId,
                'issued_by_type' => $userType,
            ]);

            Log::info('Certificate issuance logged successfully:', [
                'id' => $issuanceLog->id,
                'certificate_number' => $issuanceLog->certificate_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Certificate issued successfully',
                'data' => $issuanceLog
            ], 201);
        } catch (\Exception $e) {
            Log::error('Certificate issuance failed with exception:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to issue certificate: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getUserType()
    {
        $user = Auth::user();

        if ($user instanceof \App\Models\Admin) {
            return 'admin';
        }

        if ($user instanceof \App\Models\Staff) {
            return 'staff';
        }

        // Fallback - check the guard or other methods
        if (Auth::guard('admin')->check()) {
            return 'admin';
        }

        if (Auth::guard('staff')->check()) {
            return 'staff';
        }

        return 'unknown';
    }

    private function getUserId()
    {
        $user = Auth::user();
        return $user ? $user->id : 0; // Use 0 or null if no user
    }

    /**
     * Verify that the record exists
     */
    private function verifyRecordExists($type, $recordId)
    {
        switch ($type) {
            case 'birth':
                return BirthRecord::find($recordId);
            case 'marriage':
                return MarriageRecord::find($recordId);
            case 'death':
                return DeathRecord::find($recordId);
            default:
                return null;
        }
    }

    /**
     * Update record statistics (e.g., increment issuance count)
     */
    private function updateRecordStatistics($type, $recordId)
    {
        // You can implement statistics tracking here
        // For example, increment an issuance_count field on the record
    }

    // In CertificateIssuanceController.php
    public function index(Request $request)
    {
        try {
            $query = CertificateIssuanceLog::query();

            // Apply filters if any
            if ($request->has('certificate_type') && $request->certificate_type) {
                $query->where('certificate_type', $request->certificate_type);
            }

            if ($request->has('date_from') && $request->date_from) {
                $query->where('date_paid', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->where('date_paid', '<=', $request->date_to);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('issued_to', 'like', "%{$search}%")
                        ->orWhere('certificate_number', 'like', "%{$search}%")
                        ->orWhere('or_number', 'like', "%{$search}%");
                });
            }

            // Get paginated results
            $issuances = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $issuances->items(), // Make sure this returns the array of items
                'total' => $issuances->total(),
                'current_page' => $issuances->currentPage(),
                'per_page' => $issuances->perPage(),
                'last_page' => $issuances->lastPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching issuance history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch issuance history'
            ], 500);
        }
    }

    /**
     * Get issuance statistics
     */
    public function statistics(Request $request)
    {
        $timeframe = $request->timeframe ?? 'month'; // day, week, month, year

        $query = CertificateIssuanceLog::selectRaw('
            certificate_type,
            COUNT(*) as total_issued,
            SUM(amount_paid) as total_revenue,
            DATE(created_at) as issue_date
        ');

        // Apply timeframe filter
        switch ($timeframe) {
            case 'day':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        $statistics = $query->groupBy('certificate_type', 'issue_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }
}

<?php
// routes/api.php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BirthRecordController;
use App\Http\Controllers\CertificateIssuanceController;
use App\Http\Controllers\DeathRecordController;
use App\Http\Controllers\DocumentScanningController;
use App\Http\Controllers\MarriageRecordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'getAuthenticatedUser']);

    // Profile management
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/birth-records/statistics', [BirthRecordController::class, 'statistics']);

    // Admin only routes
    Route::prefix('admin')->group(function () {
        // Staff management
        Route::get('/staff', [StaffController::class, 'index']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::get('/staff/statistics', [StaffController::class, 'statistics']);
        Route::get('/staff/{id}', [StaffController::class, 'show']);
        Route::put('/staff/{id}', [StaffController::class, 'update']);
        Route::patch('/staff/{id}/deactivate', [StaffController::class, 'deactivate']);
        Route::patch('/staff/{id}/reactivate', [StaffController::class, 'reactivate']);
        Route::delete('/staff/{id}', [StaffController::class, 'destroy']);

        Route::post('/staff/{id}', [StaffController::class, 'update']); // Add this for FormData

        // TEST routes
        Route::put('/staff-test/{id}', [StaffController::class, 'testUpdate']);
        Route::post('/staff-test', [StaffController::class, 'testCreate']);
    });

    // Birth Records Routes
    Route::prefix('birth-records')->group(function () {
        Route::get('/', [BirthRecordController::class, 'index']);
        Route::post('/check-duplicate', [BirthRecordController::class, 'checkDuplicate']);
        Route::post('/', [BirthRecordController::class, 'store']);
        Route::get('/{id}', [BirthRecordController::class, 'show']);
        Route::put('/{id}', [BirthRecordController::class, 'update']);
        Route::delete('/{id}', [BirthRecordController::class, 'destroy']);
    });

    // Marriage Records Routes
    Route::prefix('marriage-records')->group(function () {
        Route::get('/', [MarriageRecordController::class, 'index']);
        Route::post('/check-duplicate', [MarriageRecordController::class, 'checkDuplicate']);
        Route::post('/', [MarriageRecordController::class, 'store']);
        Route::get('/{id}', [MarriageRecordController::class, 'show']);
        Route::put('/{id}', [MarriageRecordController::class, 'update']);
        Route::delete('/{id}', [MarriageRecordController::class, 'destroy']);
        Route::get('/statistics/overview', [MarriageRecordController::class, 'statistics']);
    });


    // Death Records Routes
    Route::prefix('death-records')->group(function () {
        Route::get('/', [DeathRecordController::class, 'index']);
        Route::post('/check-duplicate', [DeathRecordController::class, 'checkDuplicate']);
        Route::post('/', [DeathRecordController::class, 'store']);
        Route::get('/statistics', [DeathRecordController::class, 'statistics']);
        Route::get('/{id}', [DeathRecordController::class, 'show']);
        Route::put('/{id}', [DeathRecordController::class, 'update']);
        Route::delete('/{id}', [DeathRecordController::class, 'destroy']);
    });



    // Reports endpoints
    Route::get('/reports/statistics', [ReportController::class, 'getStatistics']);
    Route::get('/reports/registrations-trend', [ReportController::class, 'getRegistrationsTrend']);
    Route::get('/reports/gender-distribution', [ReportController::class, 'getGenderDistribution']);
    Route::get('/reports/monthly-summary', [ReportController::class, 'getMonthlySummary']);
    Route::get('/reports/record-type-distribution', [ReportController::class, 'getRecordTypeDistribution']);
    Route::get('/reports/export-data', [ReportController::class, 'exportData']);


    // Backup routes
    Route::get('/backup/info', [BackupController::class, 'getBackupInfo']);
    Route::get('/backup/schedule', [BackupController::class, 'getSchedule']);
    Route::put('/backup/schedule', [BackupController::class, 'saveSchedule']);
    Route::post('/backup/create', [BackupController::class, 'createBackup']);
    Route::get('/backup/download/{filename}', [BackupController::class, 'downloadBackup']);
    Route::delete('/backup/delete/{filename}', [BackupController::class, 'deleteBackup']);

    // Certificate issuance
    Route::post('/certificate-issuance', [CertificateIssuanceController::class, 'store']);
    Route::get('/certificate-issuance', [CertificateIssuanceController::class, 'index']);
    Route::get('/certificate-issuance/statistics', [CertificateIssuanceController::class, 'statistics']);

    // Enhanced search endpoints
    Route::get('/birth-records/search', [BirthRecordController::class, 'search']);
    Route::get('/marriage-records/search', [MarriageRecordController::class, 'search']);
    Route::get('/death-records/search', [DeathRecordController::class, 'search']);

    // Individual record endpoints (for certificate generation)
    Route::get('/birth-records/{id}', [BirthRecordController::class, 'show']);
    Route::get('/marriage-records/{id}', [MarriageRecordController::class, 'show']);
    Route::get('/death-records/{id}', [DeathRecordController::class, 'show']);


    // In routes/api.php
    Route::get('/dashboard/statistics', [ReportController::class, 'getDashboardStatistics']);

    // In your routes/api.php, inside the auth middleware group

    // Document Scanning Routes
    Route::get('/document-scanning/check-filename', [DocumentScanningController::class, 'checkFilename']);
    Route::post('/document-scanning/upload', [DocumentScanningController::class, 'uploadDocument']);
    Route::get('/document-scanning/search', [DocumentScanningController::class, 'searchDocuments']);
    Route::get('/document-scanning/documents', [DocumentScanningController::class, 'getAllDocuments']);
    Route::get('/document-scanning/document/{id}', [DocumentScanningController::class, 'getDocument']);
    Route::get('/document-scanning/file/{id}', [DocumentScanningController::class, 'serveFile']);
    // ADD THIS DELETE ROUTE:
    Route::delete('/document-scanning/documents/{id}', [DocumentScanningController::class, 'destroy']);;
});

// FIXED API Route - Remove mimeType() call
Route::get('/avatar/{filename}', function ($filename) {
    // Clean the filename - remove any "avatars/" prefix if present
    $cleanFilename = str_replace('avatars/', '', $filename);
    $cleanFilename = str_replace('avatar_', '', $cleanFilename);

    $path = 'avatars/' . $cleanFilename;

    if (!Storage::disk('public')->exists($path)) {
        Log::error("Avatar not found: " . $path);
        abort(404);
    }

    $file = Storage::disk('public')->get($path);

    // Get mime type using a different approach
    $mimeType = mime_content_type(storage_path('app/public/' . $path));

    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Cross-Origin-Resource-Policy', 'cross-origin');
});


// Add this to your routes/api.php temporarily
Route::get('/check-file/{filename}', function ($filename) {
    $path = "documents/marriage/{$filename}";

    $exists = Storage::disk('public')->exists($path);
    $fullPath = storage_path('app/public/' . $path);

    return response()->json([
        'filename' => $filename,
        'path' => $path,
        'exists' => $exists,
        'full_path' => $fullPath,
        'file_exists_php' => file_exists($fullPath),
        'is_readable' => is_readable($fullPath),
        'filesize' => $exists ? Storage::disk('public')->size($path) : 0,
    ]);
});

// Add this to your existing document-scanning routes
Route::get('/document-scanning/file/{id}', [DocumentScanningController::class, 'serveFile']);

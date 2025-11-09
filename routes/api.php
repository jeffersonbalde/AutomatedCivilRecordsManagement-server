<?php
// routes/api.php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BirthRecordController;
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

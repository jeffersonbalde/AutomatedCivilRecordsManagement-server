<?php
// app/Http/Controllers/BackupController.php
namespace App\Http\Controllers;

use App\Models\BackupSchedule;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $backupService
    ) {}

    public function getBackupInfo()
{
    try {
        $backupPath = storage_path('app/backups');
        $backups = [];
        
        if (file_exists($backupPath)) {
            $files = scandir($backupPath);
            
            // Set Philippine timezone
            $timezone = new \DateTimeZone('Asia/Manila');
            
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $backupPath . '/' . $file;
                    
                    // Get file modification time
                    $filemtime = filemtime($filePath);
                    
                    // Create DateTime object with Philippine timezone
                    $date = new \DateTime();
                    $date->setTimestamp($filemtime);
                    $date->setTimezone($timezone);
                    
                    $backups[] = [
                        'name' => $file,
                        'size' => filesize($filePath),
                        'created_at' => $date->format('Y-m-d H:i:s'), // Philippine time
                        'type' => pathinfo($file, PATHINFO_EXTENSION)
                    ];
                }
            }
        }

        // Rest of the function remains the same...
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        $databaseSize = 0;
        try {
            $result = FacadesDB::select("
                SELECT SUM(data_length + index_length) as size
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ", [env('DB_DATABASE')]);
            $databaseSize = $result[0]->size ?? 0;
        } catch (\Exception $e) {
            Log::warning('Could not fetch database size: ' . $e->getMessage());
        }

        $schedule = BackupSchedule::getCurrent();

        return response()->json([
            'success' => true,
            'data' => [
                'backups' => $backups,
                'database_size' => $databaseSize,
                'last_backup' => count($backups) > 0 ? $backups[0]['created_at'] : null,
                'backup_count' => count($backups),
                'schedule' => $schedule ? [
                    'frequency' => $schedule->frequency,
                    'run_time' => $schedule->run_time,
                    'day_of_week' => $schedule->day_of_week,
                    'is_enabled' => $schedule->is_enabled,
                ] : null,
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Backup info error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch backup information: ' . $e->getMessage()
        ], 500);
    }
}

    public function createBackup(Request $request)
    {
        try {
            $result = $this->backupService->createBackup();

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup creation failed: ' . ($result['message'] ?? 'Unknown error')
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => [
                    'filename' => $result['filename'],
                    'size' => $result['size'],
                    'created_at' => $result['created_at']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Backup creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Backup creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadBackup($filename)
    {
        try {
            // Security check - prevent directory traversal
            if (preg_match('/\.\.|\/|\\\\/', $filename)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename'
                ], 400);
            }

            $filePath = storage_path('app/backups/' . $filename);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            return response()->download($filePath);

        } catch (\Exception $e) {
            Log::error('Backup download error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Download failed'
            ], 500);
        }
    }

    public function deleteBackup($filename)
    {
        try {
            // Security check
            if (preg_match('/\.\.|\/|\\\\/', $filename)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename'
                ], 400);
            }

            $filePath = storage_path('app/backups/' . $filename);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            unlink($filePath);

            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Backup delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Delete failed'
            ], 500);
        }
    }

    /**
     * Get backup schedule (Phase 1: scheduling form).
     */
    public function getSchedule()
    {
        try {
            $schedule = BackupSchedule::getCurrent();
            return response()->json([
                'success' => true,
                'data' => $schedule ? [
                    'frequency' => $schedule->frequency,
                    'run_time' => $schedule->run_time,
                    'day_of_week' => $schedule->day_of_week,
                    'is_enabled' => $schedule->is_enabled,
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Backup schedule get error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get schedule',
            ], 500);
        }
    }

    /**
     * Save backup schedule (Phase 1: scheduling form).
     */
    public function saveSchedule(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'frequency' => 'required|in:daily,weekly',
                'run_time' => 'required|string|regex:/^\d{1,2}:\d{2}$/', // H:mm or HH:mm
                'day_of_week' => 'nullable|integer|min:0|max:6', // 0=Sunday .. 6=Saturday
                'is_enabled' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $schedule = BackupSchedule::getCurrent();
            if (!$schedule) {
                $schedule = new BackupSchedule();
            }

            $parts = explode(':', trim($request->run_time));
            $hour = isset($parts[0]) ? (int) $parts[0] : 2;
            $minute = isset($parts[1]) ? (int) $parts[1] : 0;
            $runTime = sprintf('%02d:%02d', $hour, $minute);

            $schedule->frequency = $request->frequency;
            $schedule->run_time = $runTime;
            $schedule->day_of_week = $request->frequency === 'weekly' ? (int) $request->day_of_week : null;
            $schedule->is_enabled = $request->boolean('is_enabled', true);
            $schedule->save();

            return response()->json([
                'success' => true,
                'message' => 'Backup schedule saved. Automatic backups will run according to this schedule.',
                'data' => [
                    'frequency' => $schedule->frequency,
                    'run_time' => $schedule->run_time,
                    'day_of_week' => $schedule->day_of_week,
                    'is_enabled' => $schedule->is_enabled,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Backup schedule save error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save schedule',
            ], 500);
        }
    }
}
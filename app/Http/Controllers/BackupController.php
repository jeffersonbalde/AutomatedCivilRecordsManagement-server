<?php
// app/Http/Controllers/BackupController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Log;
use PDO;

class BackupController extends Controller
{
// app/Http/Controllers/BackupController.php

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

        return response()->json([
            'success' => true,
            'data' => [
                'backups' => $backups,
                'database_size' => $databaseSize,
                'last_backup' => count($backups) > 0 ? $backups[0]['created_at'] : null,
                'backup_count' => count($backups)
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
            $backupType = $request->get('type', 'database');
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}.sql";
            $backupPath = storage_path('app/backups/' . $filename);

            // Ensure backup directory exists
            if (!file_exists(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            // Create backup using pure PHP - no external dependencies
            $this->generateMySQLDump($backupPath);

            if (!file_exists($backupPath)) {
                throw new \Exception('Backup file was not created');
            }

            // Check if file is empty
            if (filesize($backupPath) === 0) {
                unlink($backupPath);
                throw new \Exception('Backup file is empty - check database connection');
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => [
                    'filename' => $filename,
                    'size' => filesize($backupPath),
                    'created_at' => Carbon::now()->toDateTimeString()
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

    /**
     * Generate MySQL dump using pure PHP
     */
    private function generateMySQLDump($filePath)
    {
        $dumpContent = "-- MySQL Backup\n";
        $dumpContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $dumpContent .= "-- Database: " . env('DB_DATABASE') . "\n\n";
        
        // Set SQL modes
        $dumpContent .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $dumpContent .= "SET AUTOCOMMIT = 0;\n";
        $dumpContent .= "START TRANSACTION;\n";
        $dumpContent .= "SET time_zone = \"+00:00\";\n\n";

        // Get all tables
        $tables = FacadesDB::select('SHOW TABLES');
        
        foreach ($tables as $table) {
            $tableName = $table->{key($table)};
            
            $dumpContent .= "--\n";
            $dumpContent .= "-- Table structure for table `$tableName`\n";
            $dumpContent .= "--\n\n";
            
            // Drop table if exists
            $dumpContent .= "DROP TABLE IF EXISTS `$tableName`;\n";
            
            // Get table creation SQL
            $createTable = FacadesDB::select("SHOW CREATE TABLE `$tableName`");
            $dumpContent .= $createTable[0]->{'Create Table'} . ";\n\n";
            
            // Get table data
            $rows = FacadesDB::table($tableName)->get();
            
            if (count($rows) > 0) {
                $dumpContent .= "--\n";
                $dumpContent .= "-- Dumping data for table `$tableName`\n";
                $dumpContent .= "--\n\n";
                
                // Get column names
                $columns = array_keys((array) $rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                foreach ($rows as $row) {
                    $values = [];
                    foreach ((array) $row as $value) {
                        if ($value === null) {
                            $values[] = "NULL";
                        } else {
                            // Escape special characters
                            $escapedValue = str_replace(
                                ["\\", "\x00", "\n", "\r", "'", '"', "\x1a"],
                                ["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
                                $value
                            );
                            $values[] = "'" . $escapedValue . "'";
                        }
                    }
                    $dumpContent .= "INSERT INTO `$tableName` ($columnList) VALUES (" . implode(", ", $values) . ");\n";
                }
                $dumpContent .= "\n";
            }
        }

        $dumpContent .= "COMMIT;\n";

        // Write to file
        file_put_contents($filePath, $dumpContent);
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
}
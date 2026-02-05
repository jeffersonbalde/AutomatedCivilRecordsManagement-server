<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackupService
{
    /**
     * Create a database backup file.
     * @return array{success: bool, filename?: string, size?: int, created_at?: string, message?: string}
     */
    public function createBackup(): array
    {
        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}.sql";
            $backupPath = storage_path('app/backups/' . $filename);

            if (!file_exists(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            $this->generateMySQLDump($backupPath);

            if (!file_exists($backupPath)) {
                return ['success' => false, 'message' => 'Backup file was not created'];
            }

            if (filesize($backupPath) === 0) {
                unlink($backupPath);
                return ['success' => false, 'message' => 'Backup file is empty - check database connection'];
            }

            return [
                'success' => true,
                'filename' => $filename,
                'size' => filesize($backupPath),
                'created_at' => Carbon::now()->toDateTimeString(),
            ];
        } catch (\Throwable $e) {
            Log::error('Backup creation error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Remove old backups, keeping only the most recent $keep files.
     * @param int $keep Number of backups to retain (default from env BACKUP_RETENTION_COUNT or 5)
     * @return int Number of backups deleted
     */
    public function pruneOldBackups(?int $keep = null): int
    {
        $keep = $keep ?? (int) (env('BACKUP_RETENTION_COUNT', 5));
        $backupPath = storage_path('app/backups');

        if (!is_dir($backupPath)) {
            return 0;
        }

        $files = [];
        foreach (scandir($backupPath) as $file) {
            if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
                continue;
            }
            $fullPath = $backupPath . DIRECTORY_SEPARATOR . $file;
            $files[] = ['name' => $file, 'mtime' => filemtime($fullPath)];
        }

        usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        $toDelete = array_slice($files, $keep);
        $deleted = 0;

        foreach ($toDelete as $f) {
            $path = $backupPath . DIRECTORY_SEPARATOR . $f['name'];
            if (file_exists($path) && unlink($path)) {
                $deleted++;
                Log::info('Backup pruned: ' . $f['name']);
            }
        }

        return $deleted;
    }

    /**
     * Generate MySQL dump using pure PHP.
     */
    public function generateMySQLDump(string $filePath): void
    {
        $dumpContent = "-- MySQL Backup\n";
        $dumpContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $dumpContent .= "-- Database: " . env('DB_DATABASE') . "\n\n";
        $dumpContent .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $dumpContent .= "SET AUTOCOMMIT = 0;\n";
        $dumpContent .= "START TRANSACTION;\n";
        $dumpContent .= "SET time_zone = \"+00:00\";\n\n";

        $tables = DB::select('SHOW TABLES');

        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];

            $dumpContent .= "--\n-- Table structure for table `$tableName`\n--\n\n";
            $dumpContent .= "DROP TABLE IF EXISTS `$tableName`;\n";
            $createTable = DB::select("SHOW CREATE TABLE `$tableName`");
            $dumpContent .= $createTable[0]->{'Create Table'} . ";\n\n";

            $rows = DB::table($tableName)->get();
            if ($rows->isNotEmpty()) {
                $dumpContent .= "--\n-- Dumping data for table `$tableName`\n--\n\n";
                $columns = array_keys((array) $rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                foreach ($rows as $row) {
                    $values = [];
                    foreach ((array) $row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $escapedValue = str_replace(
                                ["\\", "\x00", "\n", "\r", "'", '"', "\x1a"],
                                ["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
                                (string) $value
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
        file_put_contents($filePath, $dumpContent);
    }
}

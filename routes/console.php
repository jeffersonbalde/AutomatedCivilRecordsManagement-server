<?php

use App\Models\BackupSchedule;
use App\Services\BackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 1: Manual backup (run anytime)
Artisan::command('backup:run', function () {
    $service = app(BackupService::class);
    $result = $service->createBackup();
    if ($result['success']) {
        $this->info('Backup created: ' . $result['filename']);
        $deleted = $service->pruneOldBackups();
        if ($deleted > 0) {
            $this->info("Pruned {$deleted} old backup(s).");
        }
    } else {
        $this->error('Backup failed: ' . ($result['message'] ?? 'Unknown error'));
    }
})->purpose('Create database backup and prune old backups (Phase 1 automatic backup)');

// Phase 1: Check schedule and run backup if it's time (run every minute by scheduler)
// Run in terminal:  php artisan schedule:work   (leave it open!)  Then set backup time in the app to 1–2 min from now.
Artisan::command('backup:run-scheduled', function () {
    $schedule = BackupSchedule::getCurrent();
    $tz = config('app.timezone', 'UTC');
    $now = \Carbon\Carbon::now($tz);
    $currentTime = $now->format('H:i'); // always HH:mm e.g. 07:15
    $currentDay = (int) $now->format('w');

    if (!$schedule) {
        $this->warn("[Backup] No schedule in database. Run migration.");
        return;
    }
    if (!$schedule->is_enabled) {
        $this->line("[Backup] {$currentTime} ({$tz}) – schedule disabled, skipping.");
        return;
    }

    // Normalize run_time to HH:mm so "7:15" matches "07:15"
    $parts = array_map('intval', explode(':', trim($schedule->run_time ?? '02:00')));
    $scheduleTime = sprintf('%02d:%02d', $parts[0] ?? 2, $parts[1] ?? 0);

    if ($currentTime !== $scheduleTime) {
        $this->line("[Backup] {$currentTime} ({$tz}) – next at {$scheduleTime}, skipping.");
        if ($tz === 'UTC') {
            $this->comment('   Tip: Set APP_TIMEZONE=Asia/Manila in .env so schedule time matches your clock.');
        }
        return;
    }
    if ($schedule->frequency === 'weekly' && $schedule->day_of_week !== null && (int) $schedule->day_of_week !== $currentDay) {
        $this->line("[Backup] {$currentTime} – weekly day mismatch, skipping.");
        return;
    }

    $this->info("[Backup] {$currentTime} – running scheduled backup now!");
    $service = app(BackupService::class);
    $result = $service->createBackup();
    if ($result['success']) {
        $this->info('Backup created: ' . $result['filename']);
        $service->pruneOldBackups();
    } else {
        $this->error('Backup failed: ' . ($result['message'] ?? 'Unknown error'));
    }
})->purpose('Check backup schedule and run backup if time matches (used by scheduler)');

// Run the scheduled backup check every minute
Schedule::command('backup:run-scheduled')->everyMinute();

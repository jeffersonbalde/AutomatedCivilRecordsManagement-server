<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSchedule extends Model
{
    protected $table = 'backup_schedule';

    public $timestamps = true;

    protected $fillable = [
        'frequency',
        'run_time',
        'day_of_week',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the single schedule row (id = 1).
     */
    public static function getCurrent(): ?self
    {
        return self::first();
    }
}

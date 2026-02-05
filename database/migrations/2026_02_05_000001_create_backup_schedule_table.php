<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedule', function (Blueprint $table) {
            $table->id();
            $table->enum('frequency', ['daily', 'weekly'])->default('daily');
            $table->string('run_time', 5)->default('02:00'); // HH:mm
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0=Sunday .. 6=Saturday, null when daily
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        // Single row: default schedule
        DB::table('backup_schedule')->insert([
            'frequency' => 'daily',
            'run_time' => '02:00',
            'day_of_week' => null,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedule');
    }
};

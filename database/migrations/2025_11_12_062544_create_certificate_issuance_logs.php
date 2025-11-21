<?php
// database/migrations/2025_11_12_062544_create_certificate_issuance_logs.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('certificate_issuance_logs')) {
            Schema::create('certificate_issuance_logs', function (Blueprint $table) {
                $table->id();
                $table->enum('certificate_type', ['birth', 'marriage', 'death']);
                $table->unsignedBigInteger('record_id');
                $table->string('certificate_number')->unique();
                $table->string('issued_to');
                $table->decimal('amount_paid', 8, 2);
                $table->string('or_number');
                $table->date('date_paid');
                $table->string('purpose')->nullable();
                $table->unsignedBigInteger('issued_by'); // Add this line
                $table->enum('issued_by_type', ['admin', 'staff']);
                $table->timestamps();

                // Indexes for better performance
                $table->index(['certificate_type', 'record_id']);
                $table->index('certificate_number');
                $table->index('or_number');
                $table->index('date_paid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_issuance_logs');
    }
};
<?php
// database/migrations/2024_01_01_create_document_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('document_records', function (Blueprint $table) {
            $table->id();
            $table->enum('record_type', ['birth', 'marriage', 'death']);
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('file_url');
            $table->text('extracted_text')->nullable();
            $table->bigInteger('file_size');
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['record_type', 'is_active']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_records');
    }
};
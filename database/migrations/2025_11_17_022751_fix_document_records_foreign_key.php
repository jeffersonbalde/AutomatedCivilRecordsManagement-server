<?php
// database/migrations/2024_01_01_fix_document_records_foreign_key.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('document_records', function (Blueprint $table) {
            // Drop the existing foreign key constraint (it references users table)
            $table->dropForeign(['uploaded_by']);
            
            // We'll make it nullable first, then decide which table to reference
            $table->unsignedBigInteger('uploaded_by')->nullable()->change();
            
            // Since you have multiple user types (admin, staff), we need to track the type too
            $table->string('uploader_type')->nullable()->after('uploaded_by'); // 'admin' or 'staff'
        });
    }

    public function down()
    {
        Schema::table('document_records', function (Blueprint $table) {
            $table->dropColumn('uploader_type');
            $table->unsignedBigInteger('uploaded_by')->nullable(false)->change();
            $table->foreign('uploaded_by')->references('id')->on('users');
        });
    }
};
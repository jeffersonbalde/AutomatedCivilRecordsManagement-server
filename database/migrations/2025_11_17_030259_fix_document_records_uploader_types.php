<?php
// database/migrations/2024_01_01_fix_document_records_uploader_types.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update existing records to use proper class names
        DB::table('document_records')
            ->where('uploader_type', 'admin')
            ->update(['uploader_type' => 'App\\Models\\Admin']);

        DB::table('document_records')
            ->where('uploader_type', 'staff')
            ->update(['uploader_type' => 'App\\Models\\Staff']);
    }

    public function down()
    {
        // Revert back if needed
        DB::table('document_records')
            ->where('uploader_type', 'App\\Models\\Admin')
            ->update(['uploader_type' => 'admin']);

        DB::table('document_records')
            ->where('uploader_type', 'App\\Models\\Staff')
            ->update(['uploader_type' => 'staff']);
    }
};
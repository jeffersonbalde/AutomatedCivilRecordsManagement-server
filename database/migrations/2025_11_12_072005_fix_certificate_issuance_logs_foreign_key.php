<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('certificate_issuance_logs', function (Blueprint $table) {
            // Drop the foreign key constraint that references users
            $table->dropForeign(['issued_by']);
        });
    }

    public function down()
    {
        Schema::table('certificate_issuance_logs', function (Blueprint $table) {
            // Re-add the foreign key if rolling back (though you probably don't want this)
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_parents_marriages_table.php
// CHANGE THE FILE NAME to create_parents_marriages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parents_marriages', function (Blueprint $table) { // CHANGE to plural
            $table->id();
            $table->foreignId('birth_record_id')->constrained('birth_records');
            $table->date('marriage_date')->nullable();
            $table->string('marriage_place_city')->nullable();
            $table->string('marriage_place_province')->nullable();
            $table->string('marriage_place_country')->default('Philippines');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parents_marriages'); // CHANGE to plural
    }
};
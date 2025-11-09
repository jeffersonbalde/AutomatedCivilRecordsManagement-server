<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_birth_records_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('birth_records', function (Blueprint $table) {
            $table->id();
            $table->string('registry_number')->unique();
            
            // Child Information
            $table->string('child_first_name');
            $table->string('child_middle_name')->nullable();
            $table->string('child_last_name');
            $table->enum('sex', ['Male', 'Female']);
            $table->date('date_of_birth');
            $table->time('time_of_birth')->nullable();
            $table->string('place_of_birth');
            $table->string('birth_address_house')->nullable();
            $table->string('birth_address_barangay')->nullable();
            $table->string('birth_address_city');
            $table->string('birth_address_province')->nullable();
            $table->enum('type_of_birth', ['Single', 'Twin', 'Triplet', 'Quadruplet', 'Other']);
            $table->enum('multiple_birth_order', ['First', 'Second', 'Third', 'Fourth', 'Fifth'])->nullable();
            $table->integer('birth_order');
            $table->decimal('birth_weight', 5, 2)->nullable();
            $table->text('birth_notes')->nullable();
            
            // System Fields
            $table->date('date_registered');
            $table->foreignId('encoded_by')->constrained('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes for search performance
            $table->index(['child_first_name', 'child_last_name']);
            $table->index('date_of_birth');
            $table->index('registry_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('birth_records');
    }
};
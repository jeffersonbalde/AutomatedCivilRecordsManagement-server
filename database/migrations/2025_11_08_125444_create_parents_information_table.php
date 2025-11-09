<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_parents_information_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parents_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('birth_record_id')->constrained('birth_records');
            $table->enum('parent_type', ['Mother', 'Father']);
            
            // Personal Information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('citizenship');
            $table->string('religion')->nullable();
            $table->string('occupation')->nullable();
            $table->integer('age_at_birth');
            
            // Mother-specific fields
            $table->integer('children_born_alive')->default(0);
            $table->integer('children_still_living')->default(0);
            $table->integer('children_deceased')->default(0);
            
            // Address
            $table->string('house_no')->nullable();
            $table->string('barangay');
            $table->string('city');
            $table->string('province');
            $table->string('country')->default('Philippines');
            
            $table->timestamps();
            
            $table->index(['birth_record_id', 'parent_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('parents_information');
    }
};
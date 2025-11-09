<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_birth_attendants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('birth_attendants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('birth_record_id')->constrained('birth_records');
            $table->enum('attendant_type', ['Physician', 'Nurse', 'Midwife', 'Hilot', 'Other']);
            $table->string('attendant_name');
            $table->string('attendant_license')->nullable();
            $table->text('attendant_certification');
            $table->string('attendant_address');
            $table->string('attendant_title');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('birth_attendants');
    }
};
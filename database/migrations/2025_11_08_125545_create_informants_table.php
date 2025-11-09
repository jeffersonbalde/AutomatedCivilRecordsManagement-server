<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_informants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('informants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('birth_record_id')->constrained('birth_records');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('relationship');
            $table->text('address');
            $table->boolean('certification_accepted')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('informants');
    }
};
<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_staff_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('contact_number')->nullable();
            $table->text('address')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(value: true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('admins');

            $table->index(['is_active', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff');
    }
};
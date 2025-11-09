<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_deactivation_fields_to_staff_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->text('deactivate_reason')->nullable()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('deactivate_reason');
            $table->foreignId('deactivated_by')->nullable()->constrained('admins')->after('deactivated_at');
        });
    }

    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['deactivate_reason', 'deactivated_at', 'deactivated_by']);
        });
    }
};
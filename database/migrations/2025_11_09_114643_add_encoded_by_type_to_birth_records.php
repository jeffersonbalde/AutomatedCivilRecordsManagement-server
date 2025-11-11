<?php
// database/migrations/2025_11_09_add_encoded_by_type_to_birth_records.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEncodedByTypeToBirthRecords extends Migration
{
    public function up()
    {
        Schema::table('birth_records', function (Blueprint $table) {
            $table->string('encoded_by_type')->nullable()->after('encoded_by');

            // For existing records, set the type based on the ID range or other logic
            // You might need to run a separate script to update existing records
        });
    }

    public function down()
    {
        Schema::table('birth_records', function (Blueprint $table) {
            $table->dropColumn('encoded_by_type');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('birth_records', function (Blueprint $table) {
            // Phase 3: Late registration (registered after normal period)
            $table->boolean('is_late_registration')->default(false)->after('date_registered');

            // Phase 3: Legitimacy status (legitimate / illegitimate)
            $table->string('legitimacy_status', 20)->default('Legitimate')->after('is_late_registration');
            $table->text('father_acknowledgment')->nullable()->after('legitimacy_status');

            // Phase 3: Name change (name at birth vs current name)
            $table->boolean('name_changed')->default(false)->after('father_acknowledgment');
            $table->string('current_first_name')->nullable()->after('name_changed');
            $table->string('current_middle_name')->nullable()->after('current_first_name');
            $table->string('current_last_name')->nullable()->after('current_middle_name');
        });
    }

    public function down(): void
    {
        Schema::table('birth_records', function (Blueprint $table) {
            $table->dropColumn([
                'is_late_registration',
                'legitimacy_status',
                'father_acknowledgment',
                'name_changed',
                'current_first_name',
                'current_middle_name',
                'current_last_name',
            ]);
        });
    }
};

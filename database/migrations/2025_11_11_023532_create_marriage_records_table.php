<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_marriage_records_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('marriage_records', function (Blueprint $table) {
            $table->id();
            $table->string('registry_number')->unique();

            // Basic Marriage Information
            $table->string('province');
            $table->string('city_municipality');
            $table->date('date_of_marriage');
            $table->time('time_of_marriage');
            $table->string('place_of_marriage');
            $table->enum('marriage_type', ['Civil', 'Church', 'Tribal', 'Other']);
            $table->string('license_number');
            $table->date('license_date');
            $table->string('license_place');
            $table->enum('property_regime', ['Absolute Community', 'Conjugal Partnership', 'Separation of Property', 'Other']);

            // Husband Information
            $table->string('husband_first_name');
            $table->string('husband_middle_name')->nullable();
            $table->string('husband_last_name');
            $table->date('husband_birthdate');
            $table->string('husband_birthplace');
            $table->enum('husband_sex', ['Male', 'Female']);
            $table->string('husband_citizenship');
            $table->string('husband_religion')->nullable();
            $table->enum('husband_civil_status', ['Single', 'Widowed', 'Divorced', 'Annulled']);
            $table->string('husband_occupation')->nullable();
            $table->text('husband_address');

            // Husband Parents
            $table->string('husband_father_name');
            $table->string('husband_father_citizenship');
            $table->string('husband_mother_name');
            $table->string('husband_mother_citizenship');

            // Husband Consent
            $table->string('husband_consent_giver')->nullable();
            $table->string('husband_consent_relationship')->nullable();
            $table->string('husband_consent_address')->nullable();

            // Wife Information
            $table->string('wife_first_name');
            $table->string('wife_middle_name')->nullable();
            $table->string('wife_last_name');
            $table->date('wife_birthdate');
            $table->string('wife_birthplace');
            $table->enum('wife_sex', ['Male', 'Female']);
            $table->string('wife_citizenship');
            $table->string('wife_religion')->nullable();
            $table->enum('wife_civil_status', ['Single', 'Widowed', 'Divorced', 'Annulled']);
            $table->string('wife_occupation')->nullable();
            $table->text('wife_address');

            // Wife Parents
            $table->string('wife_father_name');
            $table->string('wife_father_citizenship');
            $table->string('wife_mother_name');
            $table->string('wife_mother_citizenship');

            // Wife Consent
            $table->string('wife_consent_giver')->nullable();
            $table->string('wife_consent_relationship')->nullable();
            $table->string('wife_consent_address')->nullable();

            // Ceremony Details
            $table->string('officiating_officer');
            $table->string('officiant_title')->nullable();
            $table->string('officiant_license')->nullable();

            // Legal Basis
            $table->string('legal_basis')->nullable();
            $table->string('legal_basis_article')->nullable();

            // Witnesses
            $table->string('witness1_name');
            $table->string('witness1_address');
            $table->string('witness1_relationship')->nullable();
            $table->string('witness2_name');
            $table->string('witness2_address');
            $table->string('witness2_relationship')->nullable();

            // Additional Information
            $table->text('marriage_remarks')->nullable();

            // System Fields
            $table->date('date_registered');
            $table->foreignId('encoded_by')->constrained('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for search performance
            $table->index(['husband_first_name', 'husband_last_name']);
            $table->index(['wife_first_name', 'wife_last_name']);
            $table->index('date_of_marriage');
            $table->index('registry_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('marriage_records');
    }
};

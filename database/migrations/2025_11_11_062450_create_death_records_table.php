<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_death_records_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('death_records', function (Blueprint $table) {
            $table->id();
            $table->string('registry_number')->unique();
            
            // Personal Information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->enum('sex', ['Male', 'Female']);
            $table->enum('civil_status', ['Single', 'Married', 'Widowed', 'Divorced', 'Annulled']);
            $table->date('date_of_death');
            $table->date('date_of_birth');
            
            // Age Information
            $table->integer('age_years')->nullable();
            $table->integer('age_months')->nullable();
            $table->integer('age_days')->nullable();
            $table->integer('age_hours')->nullable();
            $table->integer('age_minutes')->nullable();
            $table->boolean('age_under_1')->default(false);
            
            // Location and Residence
            $table->text('place_of_death');
            $table->string('religion')->nullable();
            $table->string('citizenship');
            $table->text('residence');
            $table->string('occupation')->nullable();
            
            // Parents Information
            $table->string('father_name');
            $table->string('mother_maiden_name');
            
            // Medical Information
            $table->string('immediate_cause');
            $table->string('antecedent_cause')->nullable();
            $table->string('underlying_cause')->nullable();
            $table->string('other_significant_conditions')->nullable();
            $table->string('maternal_condition')->nullable();
            $table->string('manner_of_death')->nullable();
            $table->string('place_of_occurrence')->nullable();
            $table->enum('autopsy', ['Yes', 'No'])->nullable();
            
            // Attendant Information
            $table->string('attendant');
            $table->string('attendant_other')->nullable();
            $table->date('attended_from')->nullable();
            $table->date('attended_to')->nullable();
            
            // Death Certification
            $table->string('certifier_signature')->nullable();
            $table->string('certifier_name');
            $table->string('certifier_title')->nullable();
            $table->string('certifier_address')->nullable();
            $table->date('certifier_date')->nullable();
            $table->enum('attended_deceased', ['Yes', 'No'])->nullable();
            $table->string('death_occurred_time')->nullable();
            
            // Burial Details
            $table->enum('corpse_disposal', ['Burial', 'Cremation', 'Other'])->nullable();
            $table->string('burial_permit_number')->nullable();
            $table->date('burial_permit_date')->nullable();
            $table->string('transfer_permit_number')->nullable();
            $table->date('transfer_permit_date')->nullable();
            $table->string('cemetery_name')->nullable();
            $table->text('cemetery_address')->nullable();
            
            // Informant Information
            $table->string('informant_signature')->nullable();
            $table->string('informant_name');
            $table->string('informant_relationship');
            $table->string('informant_address')->nullable();
            $table->date('informant_date')->nullable();
            
            // System Fields
            $table->date('date_registered');
           $table->foreignId('encoded_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes for search performance
            $table->index(['first_name', 'last_name']);
            $table->index('date_of_death');
            $table->index('registry_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('death_records');
    }
};
<?php
// database/seeders/DeathRecordSeeder.php
namespace Database\Seeders;

use App\Models\DeathRecord;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeathRecordSeeder extends Seeder
{
    public function run()
    {
        // Get staff members to use as encoders
        $staffMembers = Staff::active()->get();
        
        if ($staffMembers->isEmpty()) {
            $this->command->warn('No active staff members found. Please run StaffSeeder first.');
            return;
        }

        $deathRecords = [];

        // Sample first names
        $firstNames = [
            'Juan', 'Maria', 'Pedro', 'Ana', 'Jose', 'Carmen', 'Antonio', 'Rosa', 
            'Manuel', 'Teresa', 'Francisco', 'Isabel', 'Carlos', 'Lourdes', 'Ricardo',
            'Elena', 'Fernando', 'Gloria', 'Miguel', 'Sofia', 'Ramon', 'Patricia',
            'Alberto', 'Mercedes', 'Roberto', 'Beatriz', 'Jorge', 'Adela', 'Luis',
            'Concepcion', 'Eduardo', 'Dolores', 'Victor', 'Angela', 'Raul', 'Margarita'
        ];

        // Sample last names
        $lastNames = [
            'Dela Cruz', 'Garcia', 'Reyes', 'Ramos', 'Mendoza', 'Santos', 'Gonzales',
            'Villanueva', 'Castillo', 'Fernandez', 'Torres', 'Rivera', 'Aquino', 
            'Cruz', 'Ortiz', 'Morales', 'Perez', 'Gomez', 'Rodriguez', 'Lopez',
            'Martinez', 'Sanchez', 'Romero', 'Navarro', 'Jimenez', 'Diaz', 'Herrera',
            'Medina', 'Castro', 'Alvarez', 'Ruiz', 'Ramirez', 'Flores', 'Bautista',
            'Vargas', 'Cortez'
        ];

        // Sample middle names
        $middleNames = [
            'Santos', 'Reyes', 'Garcia', 'Cruz', 'Mendoza', 'Ramos', 'Dela Cruz',
            'Torres', 'Rivera', 'Fernandez', 'Gonzales', 'Villanueva', 'Castillo',
            'Aquino', 'Ortiz', 'Morales', null, null, null
        ];

        // Sample places of death
        $placesOfDeath = [
            'Pagadian City Medical Center, Pagadian City, Zamboanga del Sur',
            'Zamboanga del Sur Medical Center, Pagadian City, Zamboanga del Sur',
            'Pagadian City Hospital, Pagadian City, Zamboanga del Sur',
            'St. Joseph Hospital, Pagadian City, Zamboanga del Sur',
            'At home, Balangasan District, Pagadian City, Zamboanga del Sur',
            'At home, Santiago District, Pagadian City, Zamboanga del Sur',
            'At home, Gatas District, Pagadian City, Zamboanga del Sur',
            'Pagadian City Emergency Hospital, Pagadian City, Zamboanga del Sur'
        ];

        // Sample residences
        $residences = [
            '123 Rizal Street, Balangasan, Pagadian City, Zamboanga del Sur, Philippines',
            '456 Mabini Street, Santiago, Pagadian City, Zamboanga del Sur, Philippines',
            '789 Bonifacio Street, Gatas, Pagadian City, Zamboanga del Sur, Philippines',
            '321 Luna Street, Balangasan, Pagadian City, Zamboanga del Sur, Philippines',
            '654 Burgos Street, Santiago, Pagadian City, Zamboanga del Sur, Philippines',
            '987 Gomez Street, Gatas, Pagadian City, Zamboanga del Sur, Philippines',
            '111 Aquino Street, Balangasan, Pagadian City, Zamboanga del Sur, Philippines',
            '222 Marcos Street, Santiago, Pagadian City, Zamboanga del Sur, Philippines'
        ];

        // Sample causes of death
        $immediateCauses = [
            'Cardiac Arrest',
            'Pneumonia',
            'Cerebrovascular Accident (Stroke)',
            'Myocardial Infarction',
            'Respiratory Failure',
            'Sepsis',
            'Renal Failure',
            'Liver Failure',
            'Cancer - Lung',
            'Cancer - Liver',
            'Cancer - Breast',
            'COVID-19',
            'Diabetes Complications',
            'Hypertension Complications'
        ];

        $antecedentCauses = [
            'Hypertension',
            'Diabetes Mellitus',
            'Chronic Obstructive Pulmonary Disease',
            'Coronary Artery Disease',
            'Chronic Kidney Disease',
            'Liver Cirrhosis',
            'Metastatic Cancer',
            'Alzheimer\'s Disease',
            'Parkinson\'s Disease'
        ];

        $underlyingCauses = [
            'Smoking-related complications',
            'Alcohol-related liver disease',
            'Obesity-related complications',
            'Genetic predisposition',
            'Occupational exposure',
            'Long-term medication use'
        ];

        // Sample other data
        $religions = ['Roman Catholic', 'Islam', 'Protestant', 'Iglesia ni Cristo', 'Born Again Christian', 'Seventh-day Adventist'];
        $occupations = ['Farmer', 'Teacher', 'Housewife', 'Fisherman', 'Driver', 'Nurse', 'Carpenter', 'Vendor', 'Retired', 'None'];
        $attendants = ['Private Physician', 'Public Health Officer', 'Hospital Authority', 'None'];
        $certifierTitles = ['Medical Doctor', 'Public Health Officer', 'Hospital Director', 'Medical Officer'];

        // Generate 50 death records
        for ($i = 1; $i <= 50; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $middleName = $middleNames[array_rand($middleNames)];
            $sex = rand(0, 1) ? 'Male' : 'Female';
            
            // Generate realistic dates (last 5 years)
            $dateOfDeath = Carbon::now()->subDays(rand(1, 1825))->format('Y-m-d');
            $dateOfBirth = Carbon::parse($dateOfDeath)->subYears(rand(40, 90))->subDays(rand(1, 365))->format('Y-m-d');
            
            // Calculate age
            $birthDate = Carbon::parse($dateOfBirth);
            $deathDate = Carbon::parse($dateOfDeath);
            $ageYears = $deathDate->diffInYears($birthDate);
            $ageUnder1 = $ageYears < 1;
            
            $deathRecords[] = [
                'registry_number' => 'DR-' . date('Y') . '-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'sex' => $sex,
                'civil_status' => $this->getRandomCivilStatus($ageYears),
                'date_of_death' => $dateOfDeath,
                'date_of_birth' => $dateOfBirth,
                'age_years' => $ageUnder1 ? null : $ageYears,
                'age_months' => $ageUnder1 ? rand(0, 11) : null,
                'age_days' => $ageUnder1 ? rand(0, 30) : null,
                'age_hours' => $ageUnder1 && rand(0, 1) ? rand(0, 23) : null,
                'age_minutes' => $ageUnder1 && rand(0, 1) ? rand(0, 59) : null,
                'age_under_1' => $ageUnder1,
                'place_of_death' => $placesOfDeath[array_rand($placesOfDeath)],
                'religion' => $religions[array_rand($religions)],
                'citizenship' => 'Filipino',
                'residence' => $residences[array_rand($residences)],
                'occupation' => $occupations[array_rand($occupations)],
                'father_name' => $this->generateFatherName($lastName),
                'mother_maiden_name' => $this->generateMotherName($lastName),
                
                // Medical Information
                'immediate_cause' => $immediateCauses[array_rand($immediateCauses)],
                'antecedent_cause' => rand(0, 1) ? $antecedentCauses[array_rand($antecedentCauses)] : null,
                'underlying_cause' => rand(0, 1) ? $underlyingCauses[array_rand($underlyingCauses)] : null,
                'other_significant_conditions' => rand(0, 1) ? 'Other contributing factors' : null,
                'maternal_condition' => $sex === 'Female' && $ageYears >= 15 && $ageYears <= 49 ? $this->getRandomMaternalCondition() : null,
                'manner_of_death' => rand(0, 1) ? 'Natural Causes' : null,
                'place_of_occurrence' => rand(0, 1) ? 'Hospital' : 'Home',
                'autopsy' => rand(0, 1) ? 'Yes' : 'No',
                'attendant' => $attendants[array_rand($attendants)],
                'attendant_other' => null,
                'attended_from' => rand(0, 1) ? Carbon::parse($dateOfDeath)->subDays(rand(1, 30))->format('Y-m-d') : null,
                'attended_to' => rand(0, 1) ? $dateOfDeath : null,
                
                // Death Certification
                'certifier_signature' => 'Dr. ' . $firstName . ' ' . $lastName,
                'certifier_name' => 'Dr. ' . $this->generateDoctorName(),
                'certifier_title' => $certifierTitles[array_rand($certifierTitles)],
                'certifier_address' => 'Pagadian City Medical Center, Pagadian City',
                'certifier_date' => $dateOfDeath,
                'attended_deceased' => rand(0, 1) ? 'Yes' : 'No',
                'death_occurred_time' => $this->generateRandomTime(),
                
                // Burial Details
                'corpse_disposal' => 'Burial',
                'burial_permit_number' => 'BP-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'burial_permit_date' => Carbon::parse($dateOfDeath)->addDays(rand(1, 3))->format('Y-m-d'),
                'transfer_permit_number' => rand(0, 1) ? 'TP-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT) : null,
                'transfer_permit_date' => rand(0, 1) ? Carbon::parse($dateOfDeath)->addDays(1)->format('Y-m-d') : null,
                'cemetery_name' => 'Pagadian City Public Cemetery',
                'cemetery_address' => 'Pagadian City, Zamboanga del Sur, Philippines',
                
                // Informant Information
                'informant_signature' => 'Signature of Informant',
                'informant_name' => $this->generateInformantName($firstName, $lastName),
                'informant_relationship' => $this->getRandomRelationship(),
                'informant_address' => $residences[array_rand($residences)],
                'informant_date' => $dateOfDeath,
                
                // System Fields
                'date_registered' => Carbon::parse($dateOfDeath)->addDays(rand(1, 7))->format('Y-m-d'),
                'encoded_by' => $staffMembers->random()->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert records in batches
        foreach (array_chunk($deathRecords, 25) as $chunk) {
            DeathRecord::insert($chunk);
        }

        $this->command->info('Successfully seeded 50 death records.');
    }

    private function getRandomCivilStatus($age)
    {
        if ($age < 18) {
            return 'Single';
        }
        
        $statuses = ['Single', 'Married', 'Widowed', 'Divorced'];
        $weights = [0.2, 0.6, 0.15, 0.05]; // Higher probability for married
        
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        
        for ($i = 0; $i < count($statuses); $i++) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) {
                return $statuses[$i];
            }
        }
        
        return 'Married';
    }

    private function generateFatherName($lastName)
    {
        $firstNames = ['Juan', 'Pedro', 'Jose', 'Antonio', 'Manuel', 'Francisco', 'Carlos', 'Ricardo'];
        return $firstNames[array_rand($firstNames)] . ' ' . $lastName;
    }

    private function generateMotherName($lastName)
    {
        $firstNames = ['Maria', 'Ana', 'Carmen', 'Rosa', 'Teresa', 'Isabel', 'Lourdes', 'Elena'];
        $maidenNames = ['Santos', 'Reyes', 'Garcia', 'Cruz', 'Mendoza', 'Ramos', 'Torres', 'Rivera'];
        return $firstNames[array_rand($firstNames)] . ' ' . $maidenNames[array_rand($maidenNames)];
    }

    private function getRandomMaternalCondition()
    {
        $conditions = [
            'Pregnant',
            'Pregnant, in labour',
            'Less than 42 days after delivery',
            '42 days to 1 year after delivery',
            'None of the above'
        ];
        return $conditions[array_rand($conditions)];
    }

    private function generateDoctorName()
    {
        $firstNames = ['Antonio', 'Maria', 'Roberto', 'Carmen', 'Eduardo', 'Gloria', 'Fernando', 'Patricia'];
        $lastNames = ['Santos', 'Reyes', 'Garcia', 'Cruz', 'Mendoza', 'Ramos', 'Torres', 'Rivera'];
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    private function generateRandomTime()
    {
        $hour = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $minute = str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT);
        $ampm = rand(0, 1) ? 'AM' : 'PM';
        return $hour . ':' . $minute . ' ' . $ampm;
    }

    private function generateInformantName($deceasedFirstName, $deceasedLastName)
    {
        $relationships = ['son', 'daughter', 'spouse', 'sibling'];
        $relationship = $relationships[array_rand($relationships)];
        
        switch ($relationship) {
            case 'son':
                $names = ['Juan', 'Pedro', 'Jose', 'Antonio'];
                return $names[array_rand($names)] . ' ' . $deceasedLastName;
            case 'daughter':
                $names = ['Maria', 'Ana', 'Carmen', 'Rosa'];
                return $names[array_rand($names)] . ' ' . $deceasedLastName;
            case 'spouse':
                return $deceasedFirstName . ' ' . $deceasedLastName; // Same name for simplicity
            case 'sibling':
                $names = ['Carlos', 'Elena', 'Ricardo', 'Gloria'];
                return $names[array_rand($names)] . ' ' . $deceasedLastName;
            default:
                return 'Relative ' . $deceasedLastName;
        }
    }

    private function getRandomRelationship()
    {
        $relationships = [
            'Son',
            'Daughter', 
            'Spouse',
            'Sibling',
            'Parent',
            'Relative',
            'Friend',
            'Hospital Staff'
        ];
        return $relationships[array_rand($relationships)];
    }
}
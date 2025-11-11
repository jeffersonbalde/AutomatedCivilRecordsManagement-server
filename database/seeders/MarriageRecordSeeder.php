<?php
// database/seeders/MarriageRecordSeeder.php
namespace Database\Seeders;

use App\Models\MarriageRecord;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MarriageRecordSeeder extends Seeder
{
    public function run()
    {
        // Get staff members for encoded_by field
        $staffMembers = Staff::all();
        
        if ($staffMembers->isEmpty()) {
            throw new \Exception('No staff members found. Please run StaffSeeder first.');
        }

        $marriageRecords = [
            [
                'registry_number' => 'MR-' . date('Y') . '-00001',
                'province' => 'Metro Manila',
                'city_municipality' => 'Quezon City',
                'date_of_marriage' => '2024-01-15',
                'time_of_marriage' => '14:30:00',
                'place_of_marriage' => 'Quezon City Hall',
                'marriage_type' => 'Civil',
                'license_number' => 'ML-2024-001',
                'license_date' => '2024-01-10',
                'license_place' => 'Quezon City Civil Registry',
                'property_regime' => 'Absolute Community',

                // Husband Information
                'husband_first_name' => 'Juan',
                'husband_middle_name' => 'Santos',
                'husband_last_name' => 'Dela Cruz',
                'husband_birthdate' => '1990-05-15',
                'husband_birthplace' => 'Manila',
                'husband_sex' => 'Male',
                'husband_citizenship' => 'Filipino',
                'husband_religion' => 'Roman Catholic',
                'husband_civil_status' => 'Single',
                'husband_occupation' => 'Software Engineer',
                'husband_address' => '123 Main Street, Quezon City',

                // Husband Parents
                'husband_father_name' => 'Pedro Dela Cruz',
                'husband_father_citizenship' => 'Filipino',
                'husband_mother_name' => 'Maria Santos',
                'husband_mother_citizenship' => 'Filipino',

                // Husband Consent
                'husband_consent_giver' => null,
                'husband_consent_relationship' => null,
                'husband_consent_address' => null,

                // Wife Information
                'wife_first_name' => 'Maria',
                'wife_middle_name' => 'Reyes',
                'wife_last_name' => 'Garcia',
                'wife_birthdate' => '1992-08-20',
                'wife_birthplace' => 'Makati',
                'wife_sex' => 'Female',
                'wife_citizenship' => 'Filipino',
                'wife_religion' => 'Roman Catholic',
                'wife_civil_status' => 'Single',
                'wife_occupation' => 'Teacher',
                'wife_address' => '456 Oak Avenue, Makati City',

                // Wife Parents
                'wife_father_name' => 'Antonio Garcia',
                'wife_father_citizenship' => 'Filipino',
                'wife_mother_name' => 'Teresa Reyes',
                'wife_mother_citizenship' => 'Filipino',

                // Wife Consent
                'wife_consent_giver' => null,
                'wife_consent_relationship' => null,
                'wife_consent_address' => null,

                // Ceremony Details
                'officiating_officer' => 'Hon. Jose Reyes',
                'officiant_title' => 'Mayor',
                'officiant_license' => 'MAR-REG-001',

                // Legal Basis
                'legal_basis' => 'Family Code of the Philippines',
                'legal_basis_article' => 'Article 1',

                // Witnesses
                'witness1_name' => 'Carlos Lopez',
                'witness1_address' => '789 Pine Street, Quezon City',
                'witness1_relationship' => 'Friend',
                'witness2_name' => 'Ana Torres',
                'witness2_address' => '321 Elm Street, Makati City',
                'witness2_relationship' => 'Colleague',

                // Additional
                'marriage_remarks' => 'Civil ceremony conducted at City Hall',

                // System
                'date_registered' => now(),
                'encoded_by' => $staffMembers->first()->id,
            ],
            [
                'registry_number' => 'MR-' . date('Y') . '-00002',
                'province' => 'Cavite',
                'city_municipality' => 'Dasmarinas',
                'date_of_marriage' => '2024-02-14',
                'time_of_marriage' => '10:00:00',
                'place_of_marriage' => 'St. Mary Church',
                'marriage_type' => 'Church',
                'license_number' => 'ML-2024-002',
                'license_date' => '2024-02-01',
                'license_place' => 'Dasmarinas Civil Registry',
                'property_regime' => 'Conjugal Partnership',

                // Husband Information
                'husband_first_name' => 'Michael',
                'husband_middle_name' => 'Lim',
                'husband_last_name' => 'Tan',
                'husband_birthdate' => '1988-03-10',
                'husband_birthplace' => 'Manila',
                'husband_sex' => 'Male',
                'husband_citizenship' => 'Filipino',
                'husband_religion' => 'Christian',
                'husband_civil_status' => 'Single',
                'husband_occupation' => 'Businessman',
                'husband_address' => '555 Business Ave, Dasmarinas, Cavite',

                // Husband Parents
                'husband_father_name' => 'Robert Tan',
                'husband_father_citizenship' => 'Filipino',
                'husband_mother_name' => 'Susan Lim',
                'husband_mother_citizenship' => 'Filipino',

                // Husband Consent
                'husband_consent_giver' => null,
                'husband_consent_relationship' => null,
                'husband_consent_address' => null,

                // Wife Information
                'wife_first_name' => 'Sarah',
                'wife_middle_name' => 'Gomez',
                'wife_last_name' => 'Chen',
                'wife_birthdate' => '1990-07-25',
                'wife_birthplace' => 'Cebu',
                'wife_sex' => 'Female',
                'wife_citizenship' => 'Filipino',
                'wife_religion' => 'Christian',
                'wife_civil_status' => 'Single',
                'wife_occupation' => 'Doctor',
                'wife_address' => '777 Health Street, Dasmarinas, Cavite',

                // Wife Parents
                'wife_father_name' => 'James Chen',
                'wife_father_citizenship' => 'Filipino',
                'wife_mother_name' => 'Elizabeth Gomez',
                'wife_mother_citizenship' => 'Filipino',

                // Wife Consent
                'wife_consent_giver' => null,
                'wife_consent_relationship' => null,
                'wife_consent_address' => null,

                // Ceremony Details
                'officiating_officer' => 'Fr. John Smith',
                'officiant_title' => 'Priest',
                'officiant_license' => 'CHR-REG-001',

                // Legal Basis
                'legal_basis' => 'Family Code of the Philippines',
                'legal_basis_article' => 'Article 1',

                // Witnesses
                'witness1_name' => 'David Wilson',
                'witness1_address' => '888 Friendship Blvd, Cavite',
                'witness1_relationship' => 'Best Man',
                'witness2_name' => 'Lisa Brown',
                'witness2_address' => '999 Sisterhood St, Cavite',
                'witness2_relationship' => 'Maid of Honor',

                // Additional
                'marriage_remarks' => 'Church wedding with traditional ceremony',

                // System
                'date_registered' => now(),
                'encoded_by' => $staffMembers->random()->id,
            ]
        ];

        foreach ($marriageRecords as $record) {
            MarriageRecord::create($record);
        }

        // Create additional random records for testing
        $this->createRandomRecords($staffMembers);

        $this->command->info('Marriage records seeded successfully!');
    }

    private function createRandomRecords($staffMembers)
    {
        $firstNamesMale = ['John', 'Michael', 'David', 'James', 'Robert', 'Christopher', 'Daniel', 'Paul', 'Mark', 'Andrew'];
        $firstNamesFemale = ['Mary', 'Jennifer', 'Lisa', 'Susan', 'Karen', 'Nancy', 'Michelle', 'Jessica', 'Sarah', 'Emily'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
        $provinces = ['Metro Manila', 'Cavite', 'Laguna', 'Bulacan', 'Rizal', 'Batangas', 'Pampanga', 'Quezon'];
        $cities = [
            'Metro Manila' => ['Quezon City', 'Manila', 'Makati', 'Taguig', 'Pasig'],
            'Cavite' => ['Dasmarinas', 'Bacoor', 'Imus', 'General Trias'],
            'Laguna' => ['Calamba', 'Santa Rosa', 'San Pedro', 'Biñan'],
            'Bulacan' => ['Malolos', 'Meycauayan', 'San Jose del Monte'],
            'Rizal' => ['Antipolo', 'Taytay', 'Cainta'],
            'Batangas' => ['Batangas City', 'Lipa', 'Tanauan'],
            'Pampanga' => ['San Fernando', 'Angeles', 'Mabalacat'],
            'Quezon' => ['Lucena', 'Tayabas', 'Candelaria']
        ];
        $marriageTypes = ['Civil', 'Church', 'Tribal', 'Other'];
        $propertyRegimes = ['Absolute Community', 'Conjugal Partnership', 'Separation of Property', 'Other'];

        for ($i = 3; $i <= 20; $i++) {
            $province = $provinces[array_rand($provinces)];
            $city = $cities[$province][array_rand($cities[$province])];
            
            $husbandFirstName = $firstNamesMale[array_rand($firstNamesMale)];
            $wifeFirstName = $firstNamesFemale[array_rand($firstNamesFemale)];
            $lastName = $lastNames[array_rand($lastNames)];

            $marriageDate = Carbon::now()->subDays(rand(1, 365));

            MarriageRecord::create([
                'registry_number' => 'MR-' . date('Y') . '-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'province' => $province,
                'city_municipality' => $city,
                'date_of_marriage' => $marriageDate->format('Y-m-d'),
                'time_of_marriage' => sprintf('%02d:%02d:00', rand(8, 17), rand(0, 59)),
                'place_of_marriage' => $city . ' ' . (rand(0, 1) ? 'City Hall' : 'Church'),
                'marriage_type' => $marriageTypes[array_rand($marriageTypes)],
                'license_number' => 'ML-' . $marriageDate->format('Y') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'license_date' => $marriageDate->copy()->subDays(rand(7, 30))->format('Y-m-d'),
                'license_place' => $city . ' Civil Registry',
                'property_regime' => $propertyRegimes[array_rand($propertyRegimes)],

                // Husband Information
                'husband_first_name' => $husbandFirstName,
                'husband_middle_name' => rand(0, 1) ? $lastNames[array_rand($lastNames)] : null,
                'husband_last_name' => $lastName,
                'husband_birthdate' => Carbon::now()->subYears(rand(25, 40))->format('Y-m-d'),
                'husband_birthplace' => $city . ', ' . $province,
                'husband_sex' => 'Male',
                'husband_citizenship' => 'Filipino',
                'husband_religion' => rand(0, 1) ? 'Roman Catholic' : 'Christian',
                'husband_civil_status' => 'Single',
                'husband_occupation' => $this->getRandomOccupation(),
                'husband_address' => rand(100, 999) . ' ' . $lastNames[array_rand($lastNames)] . ' Street, ' . $city,

                // Husband Parents
                'husband_father_name' => $firstNamesMale[array_rand($firstNamesMale)] . ' ' . $lastName,
                'husband_father_citizenship' => 'Filipino',
                'husband_mother_name' => $firstNamesFemale[array_rand($firstNamesFemale)] . ' ' . $lastNames[array_rand($lastNames)],
                'husband_mother_citizenship' => 'Filipino',

                // Wife Information
                'wife_first_name' => $wifeFirstName,
                'wife_middle_name' => rand(0, 1) ? $lastNames[array_rand($lastNames)] : null,
                'wife_last_name' => $lastNames[array_rand($lastNames)],
                'wife_birthdate' => Carbon::now()->subYears(rand(23, 35))->format('Y-m-d'),
                'wife_birthplace' => $city . ', ' . $province,
                'wife_sex' => 'Female',
                'wife_citizenship' => 'Filipino',
                'wife_religion' => rand(0, 1) ? 'Roman Catholic' : 'Christian',
                'wife_civil_status' => 'Single',
                'wife_occupation' => $this->getRandomOccupation(),
                'wife_address' => rand(100, 999) . ' ' . $lastNames[array_rand($lastNames)] . ' Avenue, ' . $city,

                // Wife Parents
                'wife_father_name' => $firstNamesMale[array_rand($firstNamesMale)] . ' ' . $lastNames[array_rand($lastNames)],
                'wife_father_citizenship' => 'Filipino',
                'wife_mother_name' => $firstNamesFemale[array_rand($firstNamesFemale)] . ' ' . $lastNames[array_rand($lastNames)],
                'wife_mother_citizenship' => 'Filipino',

                // Ceremony Details
                'officiating_officer' => (rand(0, 1) ? 'Hon. ' : 'Fr. ') . $firstNamesMale[array_rand($firstNamesMale)] . ' ' . $lastNames[array_rand($lastNames)],
                'officiant_title' => rand(0, 1) ? 'Mayor' : 'Priest',
                'officiant_license' => 'LIC-' . strtoupper(substr($city, 0, 3)) . '-' . rand(1000, 9999),

                // Witnesses
                'witness1_name' => $firstNamesMale[array_rand($firstNamesMale)] . ' ' . $lastNames[array_rand($lastNames)],
                'witness1_address' => rand(100, 999) . ' Witness Street, ' . $city,
                'witness1_relationship' => 'Friend',
                'witness2_name' => $firstNamesFemale[array_rand($firstNamesFemale)] . ' ' . $lastNames[array_rand($lastNames)],
                'witness2_address' => rand(100, 999) . ' Witness Avenue, ' . $city,
                'witness2_relationship' => 'Relative',

                // System
                'date_registered' => $marriageDate->copy()->addDays(rand(1, 7)),
                'encoded_by' => $staffMembers->random()->id,
            ]);
        }
    }

    private function getRandomOccupation()
    {
        $occupations = [
            'Teacher', 'Engineer', 'Doctor', 'Nurse', 'Accountant', 'Sales Manager',
            'Software Developer', 'Business Owner', 'Architect', 'Police Officer',
            'Firefighter', 'Chef', 'Artist', 'Writer', 'Musician', 'Farmer'
        ];
        return $occupations[array_rand($occupations)];
    }
}
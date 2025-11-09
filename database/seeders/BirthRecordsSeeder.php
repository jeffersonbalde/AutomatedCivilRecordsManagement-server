<?php
// database/seeders/BirthRecordsSeeder.php
namespace Database\Seeders;

use App\Models\BirthRecord;
use App\Models\ParentsInformation;
use App\Models\ParentsMarriage;
use App\Models\BirthAttendant;
use App\Models\Informant;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BirthRecordsSeeder extends Seeder
{
    public function run()
    {
        // Get a staff member to use as encoded_by
        $staff = Staff::first();

        if (!$staff) {
            $this->command->info('No staff found. Please run StaffSeeder first.');
            return;
        }

        $birthRecords = [
            [
                'registry_number' => 'BR-' . date('Y') . '-00001',
                'child_first_name' => 'Juan',
                'child_middle_name' => 'Dela',
                'child_last_name' => 'Cruz',
                'sex' => 'Male',
                'date_of_birth' => '2024-01-15',
                'time_of_birth' => '08:30:00',
                'place_of_birth' => 'Pagadian City Medical Center',
                'birth_address_house' => '123 Main Street',
                'birth_address_barangay' => 'Balangasan',
                'birth_address_city' => 'Pagadian City',
                'birth_address_province' => 'Zamboanga del Sur',
                'type_of_birth' => 'Single',
                'multiple_birth_order' => null,
                'birth_order' => 1,
                'birth_weight' => 3.2,
                'birth_notes' => 'Normal delivery, healthy baby',
                'date_registered' => now(),
                'encoded_by' => $staff->id,
            ],
            [
                'registry_number' => 'BR-' . date('Y') . '-00002',
                'child_first_name' => 'Maria',
                'child_middle_name' => 'Santos',
                'child_last_name' => 'Reyes',
                'sex' => 'Female',
                'date_of_birth' => '2024-02-20',
                'time_of_birth' => '14:15:00',
                'place_of_birth' => 'Pagadian City Hospital',
                'birth_address_house' => '456 Oak Avenue',
                'birth_address_barangay' => 'San Pedro',
                'birth_address_city' => 'Pagadian City',
                'birth_address_province' => 'Zamboanga del Sur',
                'type_of_birth' => 'Twin',
                'multiple_birth_order' => 'First',
                'birth_order' => 2,
                'birth_weight' => 2.8,
                'birth_notes' => 'Twin birth, first child',
                'date_registered' => now(),
                'encoded_by' => $staff->id,
            ],
        ];

        foreach ($birthRecords as $birthData) {
            $birthRecord = BirthRecord::create($birthData);

            // Create mother information
            ParentsInformation::create([
                'birth_record_id' => $birthRecord->id,
                'parent_type' => 'Mother',
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
                'last_name' => 'Reyes',
                'citizenship' => 'Filipino',
                'religion' => 'Roman Catholic',
                'occupation' => 'Teacher',
                'age_at_birth' => 28,
                'children_born_alive' => 2,
                'children_still_living' => 2,
                'children_deceased' => 0,
                'house_no' => '456 Oak Avenue',
                'barangay' => 'San Pedro',
                'city' => 'Pagadian City',
                'province' => 'Zamboanga del Sur',
                'country' => 'Philippines',
            ]);

            // Create father information
            ParentsInformation::create([
                'birth_record_id' => $birthRecord->id,
                'parent_type' => 'Father',
                'first_name' => 'Antonio',
                'middle_name' => 'Dela',
                'last_name' => 'Cruz',
                'citizenship' => 'Filipino',
                'religion' => 'Roman Catholic',
                'occupation' => 'Engineer',
                'age_at_birth' => 32,
                'children_born_alive' => 2,
                'children_still_living' => 2,
                'children_deceased' => 0,
                'house_no' => '456 Oak Avenue',
                'barangay' => 'San Pedro',
                'city' => 'Pagadian City',
                'province' => 'Zamboanga del Sur',
                'country' => 'Philippines',
            ]);

            // Create parents marriage information
            ParentsMarriage::create([
                'birth_record_id' => $birthRecord->id,
                'marriage_date' => '2020-05-15',
                'marriage_place_city' => 'Pagadian City',
                'marriage_place_province' => 'Zamboanga del Sur',
                'marriage_place_country' => 'Philippines',
            ]);

            // Create birth attendant
            BirthAttendant::create([
                'birth_record_id' => $birthRecord->id,
                'attendant_type' => 'Physician',
                'attendant_name' => 'Dr. Roberto Santos',
                'attendant_license' => 'PRC-123456',
                'attendant_certification' => 'I hereby certify that I attended the birth of the child who was born alive at the time and date specified above.',
                'attendant_address' => 'Pagadian City Medical Center, Pagadian City',
                'attendant_title' => 'Medical Doctor',
            ]);

            // Create informant
            Informant::create([
                'birth_record_id' => $birthRecord->id,
                'first_name' => 'Antonio',
                'middle_name' => 'Dela',
                'last_name' => 'Cruz',
                'relationship' => 'Parent',
                'address' => '456 Oak Avenue, San Pedro, Pagadian City',
                'certification_accepted' => true,
            ]);
        }

        $this->command->info('Birth records seeded successfully!');
    }
}
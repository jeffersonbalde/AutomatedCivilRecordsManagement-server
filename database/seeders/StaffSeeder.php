<?php
// database/seeders/StaffSeeder.php
namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run()
    {
        $staffMembers = [
            [
                'email' => 'staff@example.com',
                'password' => Hash::make('password123'),
                'full_name' => 'John Doe',
                'contact_number' => '09123456789',
                'address' => '123 Main Street, Barangay 1, City, Province',
                'is_active' => true,
                'created_by' => 1,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'encoder@example.com',
                'password' => Hash::make('password123'),
                'full_name' => 'Jane Smith',
                'contact_number' => '09198765432',
                'address' => '456 Oak Street, Barangay 2, City, Province',
                'is_active' => true,
                'created_by' => 1,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'manager@example.com',
                'password' => Hash::make('password123'),
                'full_name' => 'Michael Johnson',
                'contact_number' => '09151234567',
                'address' => '789 Pine Street, Barangay 3, City, Province',
                'is_active' => true,
                'created_by' => 1,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'inactive@example.com',
                'password' => Hash::make('password123'),
                'full_name' => 'Sarah Wilson',
                'contact_number' => '09169874532',
                'address' => '321 Elm Street, Barangay 4, City, Province',
                'is_active' => false,
                'created_by' => 1,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($staffMembers as $staff) {
            Staff::create($staff);
        }
    }
}
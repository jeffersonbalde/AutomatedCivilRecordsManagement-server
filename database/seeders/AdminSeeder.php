<?php
// database/seeders/AdminSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class AdminSeeder extends Seeder
{
    public function run()
    {
        Admin::create([
            'username' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('123456'),
            'full_name' => 'System Administrator',
            'admin_id' => 'ADM001',
            'position' => 'System Administrator',
        ]);

        $this->command->info('Default admin account created!');
        $this->command->info('Username: admin / Password: 123456');
    }
}
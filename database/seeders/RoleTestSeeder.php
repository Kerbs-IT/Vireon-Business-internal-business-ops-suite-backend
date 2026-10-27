<?php

namespace Database\Seeders;

use App\Models\Roles;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure the three roles exist first (users.role_id depends on this)
        $adminRole    = Roles::firstOrCreate(['name' => 'admin']);
        $managerRole  = Roles::firstOrCreate(['name' => 'manager']);
        $employeeRole = Roles::firstOrCreate(['name' => 'employee']);

        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'position' => 'System Administrator',
                'date_hired' => now(),
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@test.com'],
            [
                'first_name' => 'Manager',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'role_id' => $managerRole->id,
                'position' => 'Project Manager',
                'date_hired' => now(),
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'employee@test.com'],
            [
                'first_name' => 'Employee',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'role_id' => $employeeRole->id,
                'position' => 'Software Developer',
                'date_hired' => now(),
                'status' => 'active',
            ]
        );
    }
}

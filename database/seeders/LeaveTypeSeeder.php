<?php

namespace Database\Seeders;

use App\Models\Leave_types;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Leave_types::create([
            'name' => 'Sick Leave',
            'default_days' => 1
        ]);
        Leave_types::create([
            'name' => 'Vacation Leave',
            'default_days' => 7
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Manager;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Manager::count() > 0)
            return;

        Manager::create([
            'crm_id' => 0,
            'role_id' => 1,
        ]);
    }
}

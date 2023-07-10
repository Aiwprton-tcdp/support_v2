<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names = ['system', 'manager', 'moderator', 'analyst'];
        $c = Role::count();

        for ($i = $c; $i < count($names); $i++) {
            Role::create(['name' => $names[$i]]);
        }
    }
}

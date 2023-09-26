<?php

namespace Database\Seeders;

use App\Models\BxCrm;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BxCrmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $domains = ['xn--24-9kc.xn--d1ao9c.xn--p1ai', 'xn--80aaoqfj1a.xn--p1ai', 'xn--24-9kc.xn--80adt5agg2byc.xn--80asehdb'];
        $acronyms = ['ЮДЛ', 'V', 'ВЮ'];
        $names = ['Юрист для людей', 'Врезерв', 'Ваш юрист'];
        $c = BxCrm::count();

        for ($i = $c; $i < count($acronyms); $i++) {
            BxCrm::create([
                'name' => $names[$i],
                'acronym' => $acronyms[$i],
                'domain' => $domains[$i]
            ]);
        }
    }
}

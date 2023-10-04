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
        $domains = [
            'xn--24-9kc.xn--d1ao9c.xn--p1ai',
            'xn--80aaoqfj1a.xn--p1ai',
            'xn--24-9kc.xn--80adt5agg2byc.xn--80asehdb'
        ];
        $app_domains = [
            'https://support.aiwprton.sms19.ru',
            'https://support_test.aiwprton.sms19.ru',
            'https://support_vu.aiwprton.sms19.ru'
        ];
        $marketplace_ids = [166, 41, 2];
        $webhooks = [
            '10033/t8swdg5q7trw0vst',
            '10/86v5bz5tbr1c9xhq',
            '10/86v5bz5tbr1c9xhq'
        ];
        $acronyms = ['ЮДЛ', 'V', 'ВЮ'];
        $names = ['Юрист для людей', 'Врезерв', 'Ваш юрист'];
        $c = BxCrm::count();

        for ($i = $c; $i < count($acronyms); $i++) {
            BxCrm::create([
                'name' => $names[$i],
                'acronym' => $acronyms[$i],
                'domain' => $domains[$i],
                'app_domain' => $app_domains[$i],
                'marketplace_id' => $marketplace_ids[$i],
                'webhook_id' => $webhooks[$i]
            ]);
        }
    }
}
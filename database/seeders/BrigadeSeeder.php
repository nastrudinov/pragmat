<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrigadeSeeder extends Seeder
{
    public function run(): void
    {
        $brigades = [
            ['name' => 'Бригада №1 (Электромонтажная)', 'leader_employee_id' => null],
            ['name' => 'Бригада №2 (Сантехническая)', 'leader_employee_id' => null],
            ['name' => 'Бригада №3 (Сварочная)', 'leader_employee_id' => null],
            ['name' => 'Бригада №4 (Ремонтная)', 'leader_employee_id' => null],
            ['name' => 'Бригада №5 (Строительная)', 'leader_employee_id' => null],
            ['name' => 'Оперативная бригада', 'leader_employee_id' => null],
            ['name' => 'Аварийная бригада', 'leader_employee_id' => null],
        ];
        
        foreach ($brigades as $brigade) {
            DB::table('brigades')->insert(array_merge($brigade, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}
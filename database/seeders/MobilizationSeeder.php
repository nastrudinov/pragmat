<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MobilizationSeeder extends Seeder
{
    public function run(): void
    {
        $stages = DB::table('mobilization_stages')->pluck('id', 'name')->toArray();
        $employees = DB::table('employees')->pluck('id', 'full_name')->toArray();
        
        $mobilizations = [
            [
                'title' => 'Модернизация оборудования на ТЭЦ-1',
                'object_name' => 'ТЭЦ-1, г. Москва',
                'start_date' => Carbon::now()->addDays(5),
                'end_date' => Carbon::now()->addMonths(2),
                'status' => 'active',
                'current_stage_id' => $stages['Подготовка документов'] ?? null,
                'created_by' => $employees['Иванов Иван Иванович'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Ремонтные работы на подстанции 220кВ',
                'object_name' => 'ПС Северная, г. Санкт-Петербург',
                'start_date' => Carbon::now()->addDays(15),
                'end_date' => Carbon::now()->addMonths(1),
                'status' => 'active',
                'current_stage_id' => $stages['Формирование бригады'] ?? null,
                'created_by' => $employees['Петров Петр Петрович'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Строительство новой ЛЭП',
                'object_name' => 'Трасса ЛЭП-500кВ, Московская область',
                'start_date' => Carbon::now()->addMonths(2),
                'end_date' => Carbon::now()->addMonths(6),
                'status' => 'active',
                'current_stage_id' => $stages['Согласование'] ?? null,
                'created_by' => $employees['Иванов Иван Иванович'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Плановое ТО оборудования',
                'object_name' => 'ГТЭС Южная',
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addDays(20),
                'status' => 'blocked',
                'current_stage_id' => $stages['Проверка компетенций'] ?? null,
                'created_by' => $employees['Сидоров Алексей Владимирович'] ?? null,
                'created_at' => Carbon::now()->subDays(20),
                'updated_at' => now()
            ],
            [
                'title' => 'Аварийно-восстановительные работы',
                'object_name' => 'ТП-456, г. Норильск',
                'start_date' => Carbon::now()->subMonths(1),
                'end_date' => Carbon::now()->subDays(5),
                'status' => 'completed',
                'current_stage_id' => $stages['Закрытие'] ?? null,
                'created_by' => $employees['Петров Петр Петрович'] ?? null,
                'created_at' => Carbon::now()->subMonths(2),
                'updated_at' => Carbon::now()->subDays(5)
            ],
        ];
        
        foreach ($mobilizations as $mobilization) {
            DB::table('mobilizations')->insert($mobilization);
        }
    }
}
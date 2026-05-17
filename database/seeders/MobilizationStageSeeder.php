<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MobilizationStageSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('mobilization_stages')->insert([
            [
                'name' => 'Подготовка документов',
                'sla_hours' => 24,
                'description' => 'Сбор и подготовка необходимой документации',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Согласование',
                'sla_hours' => 48,
                'description' => 'Согласование с руководством',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Формирование бригады',
                'sla_hours' => 72,
                'description' => 'Подбор сотрудников в бригаду',
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Проверка компетенций',
                'sla_hours' => 48,
                'description' => 'Проверка наличия необходимых обучений',
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Назначение',
                'sla_hours' => 24,
                'description' => 'Назначение сотрудников на объект',
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Выполнение работ',
                'sla_hours' => null,
                'description' => 'Выполнение работ на объекте',
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Закрытие',
                'sla_hours' => 24,
                'description' => 'Завершение и закрытие мобилизации',
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
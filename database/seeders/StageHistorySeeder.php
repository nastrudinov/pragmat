<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StageHistorySeeder extends Seeder
{
    public function run(): void
    {
        // Получаем все мобилизации
        $mobilizations = DB::table('mobilizations')->get();
        $stages = DB::table('mobilization_stages')->get();
        
        // Создаем карты для поиска
        $mobilizationMap = [];
        foreach ($mobilizations as $mobilization) {
            $mobilizationMap[$mobilization->title] = $mobilization->id;
        }
        
        $stageMap = [];
        foreach ($stages as $stage) {
            $stageMap[$stage->name] = $stage->id;
        }
        
        // История для завершенной мобилизации
        $completedMobId = $mobilizationMap['Аварийно-восстановительные работы'] ?? null;
        
        if ($completedMobId) {
            $histories = [
                [
                    'mobilization_id' => $completedMobId,
                    'stage_id' => $stageMap['Подготовка документов'] ?? null,
                    'started_at' => Carbon::now()->subMonths(2),
                    'completed_at' => Carbon::now()->subMonths(2)->addDays(1),
                    'status' => 'completed',
                    'notes' => 'Документы подготовлены'
                ],
                [
                    'mobilization_id' => $completedMobId,
                    'stage_id' => $stageMap['Согласование'] ?? null,
                    'started_at' => Carbon::now()->subMonths(2)->addDays(1),
                    'completed_at' => Carbon::now()->subMonths(2)->addDays(2),
                    'status' => 'completed',
                    'notes' => 'Согласовано руководством'
                ],
                [
                    'mobilization_id' => $completedMobId,
                    'stage_id' => $stageMap['Формирование бригады'] ?? null,
                    'started_at' => Carbon::now()->subMonths(2)->addDays(2),
                    'completed_at' => Carbon::now()->subMonths(2)->addDays(4),
                    'status' => 'completed',
                    'notes' => 'Бригада сформирована'
                ],
                [
                    'mobilization_id' => $completedMobId,
                    'stage_id' => $stageMap['Проверка компетенций'] ?? null,
                    'started_at' => Carbon::now()->subMonths(2)->addDays(4),
                    'completed_at' => Carbon::now()->subMonths(2)->addDays(5),
                    'status' => 'completed',
                    'notes' => 'Компетенции проверены'
                ],
                [
                    'mobilization_id' => $completedMobId,
                    'stage_id' => $stageMap['Назначение'] ?? null,
                    'started_at' => Carbon::now()->subMonths(2)->addDays(5),
                    'completed_at' => Carbon::now()->subMonths(2)->addDays(6),
                    'status' => 'completed',
                    'notes' => 'Сотрудники назначены'
                ],
                [
                    'mobilization_id' => $completedMobId,
                    'stage_id' => $stageMap['Выполнение работ'] ?? null,
                    'started_at' => Carbon::now()->subMonths(2)->addDays(6),
                    'completed_at' => Carbon::now()->subDays(10),
                    'status' => 'completed',
                    'notes' => 'Работы выполнены в срок'
                ],
                [
                    'mobilization_id' => $completedMobId,
                    'stage_id' => $stageMap['Закрытие'] ?? null,
                    'started_at' => Carbon::now()->subDays(10),
                    'completed_at' => Carbon::now()->subDays(5),
                    'status' => 'completed',
                    'notes' => 'Мобилизация закрыта'
                ],
            ];
            
            foreach ($histories as $history) {
                if ($history['stage_id']) {
                    DB::table('stage_histories')->insert(array_merge($history, [
                        'created_at' => now(),
                        'updated_at' => now()
                    ]));
                }
            }
        }
    }
}
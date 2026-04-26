<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MobilizationEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // Получаем все мобилизации с их ID и названиями
        $mobilizations = DB::table('mobilizations')->get();
        $employees = DB::table('employees')->get();
        
        // Создаем массивы для быстрого поиска
        $mobilizationMap = [];
        foreach ($mobilizations as $mobilization) {
            $mobilizationMap[$mobilization->title] = $mobilization->id;
        }
        
        $employeeMap = [];
        foreach ($employees as $employee) {
            $employeeMap[$employee->full_name] = $employee->id;
        }
        
        $assignments = [];
        
        // Модернизация оборудования на ТЭЦ-1
        $mobId = $mobilizationMap['Модернизация оборудования на ТЭЦ-1'] ?? null;
        if ($mobId) {
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Сидоров Алексей Владимирович'] ?? null,
                'role' => 'Руководитель проекта'
            ];
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Кузнецов Дмитрий Сергеевич'] ?? null,
                'role' => 'Ответственный исполнитель'
            ];
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Волков Андрей Михайлович'] ?? null,
                'role' => 'Исполнитель'
            ];
        }
        
        // Ремонтные работы на подстанции 220кВ
        $mobId = $mobilizationMap['Ремонтные работы на подстанции 220кВ'] ?? null;
        if ($mobId) {
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Кузнецов Дмитрий Сергеевич'] ?? null,
                'role' => 'Руководитель'
            ];
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Морозов Сергей Александрович'] ?? null,
                'role' => 'Слесарь'
            ];
        }
        
        // Строительство новой ЛЭП
        $mobId = $mobilizationMap['Строительство новой ЛЭП'] ?? null;
        if ($mobId) {
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Сидоров Алексей Владимирович'] ?? null,
                'role' => 'Руководитель'
            ];
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Кузнецов Дмитрий Сергеевич'] ?? null,
                'role' => 'Инженер'
            ];
        }
        
        // Плановое ТО оборудования
        $mobId = $mobilizationMap['Плановое ТО оборудования'] ?? null;
        if ($mobId) {
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Морозов Сергей Александрович'] ?? null,
                'role' => 'Ответственный'
            ];
        }
        
        // Аварийно-восстановительные работы
        $mobId = $mobilizationMap['Аварийно-восстановительные работы'] ?? null;
        if ($mobId) {
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Соколов Олег Иванович'] ?? null,
                'role' => 'Сварщик'
            ];
            $assignments[] = [
                'mobilization_id' => $mobId,
                'employee_id' => $employeeMap['Волков Андрей Михайлович'] ?? null,
                'role' => 'Электромонтер'
            ];
        }
        
        // Вставляем только валидные записи
        foreach ($assignments as $assignment) {
            if ($assignment['mobilization_id'] && $assignment['employee_id']) {
                DB::table('mobilization_employees')->insert([
                    'mobilization_id' => $assignment['mobilization_id'],
                    'employee_id' => $assignment['employee_id'],
                    'role' => $assignment['role'],
                    'assigned_at' => now()
                ]);
            }
        }
    }
}
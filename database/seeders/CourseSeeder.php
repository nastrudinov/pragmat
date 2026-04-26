<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = DB::table('course_categories')->pluck('id', 'name')->toArray();
        
        $courses = [
            [
                'name' => 'Охрана труда',
                'category_id' => $categories['Минимально-Обязательные'],
                'duration_hours' => 40,
                'periodicity_months' => 12,
                'description' => 'Базовый курс по охране труда для всех сотрудников'
            ],
            [
                'name' => 'Пожарная безопасность',
                'category_id' => $categories['Минимально-Обязательные'],
                'duration_hours' => 24,
                'periodicity_months' => 12,
                'description' => 'Обучение мерам пожарной безопасности'
            ],
            [
                'name' => 'Электробезопасность',
                'category_id' => $categories['Технические'],
                'duration_hours' => 72,
                'periodicity_months' => 12,
                'description' => 'Курс по электробезопасности для электротехнического персонала'
            ],
            [
                'name' => 'Работа на высоте',
                'category_id' => $categories['Технические'],
                'duration_hours' => 40,
                'periodicity_months' => 12,
                'description' => 'Безопасные методы работы на высоте'
            ],
            [
                'name' => 'Управление проектами',
                'category_id' => $categories['Проекты'],
                'duration_hours' => 80,
                'periodicity_months' => 24,
                'description' => 'Основы управления проектами'
            ],
            [
                'name' => 'Первая помощь',
                'category_id' => $categories['Дополнительные'],
                'duration_hours' => 16,
                'periodicity_months' => 24,
                'description' => 'Оказание первой помощи пострадавшим'
            ],
            [
                'name' => 'Сварочные работы',
                'category_id' => $categories['Технические'],
                'duration_hours' => 120,
                'periodicity_months' => 12,
                'description' => 'Технология сварочных работ'
            ],
            [
                'name' => 'Экологическая безопасность',
                'category_id' => $categories['Минимально-Обязательные'],
                'duration_hours' => 32,
                'periodicity_months' => 12,
                'description' => 'Охрана окружающей среды'
            ],
        ];
        
        foreach ($courses as $course) {
            DB::table('courses')->insert(array_merge($course, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}
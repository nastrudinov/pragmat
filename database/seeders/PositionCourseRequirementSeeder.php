<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionCourseRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $positions = DB::table('positions')->pluck('id', 'name')->toArray();
        $courses = DB::table('courses')->pluck('id', 'name')->toArray();
        
        $requirements = [
            // Для электромонтера
            ['position_id' => $positions['Электромонтер'], 'course_id' => $courses['Охрана труда'], 'is_required' => true],
            ['position_id' => $positions['Электромонтер'], 'course_id' => $courses['Электробезопасность'], 'is_required' => true],
            ['position_id' => $positions['Электромонтер'], 'course_id' => $courses['Пожарная безопасность'], 'is_required' => true],
            ['position_id' => $positions['Электромонтер'], 'course_id' => $courses['Работа на высоте'], 'is_required' => true],
            
            // Для сварщика
            ['position_id' => $positions['Сварщик'], 'course_id' => $courses['Охрана труда'], 'is_required' => true],
            ['position_id' => $positions['Сварщик'], 'course_id' => $courses['Сварочные работы'], 'is_required' => true],
            ['position_id' => $positions['Сварщик'], 'course_id' => $courses['Пожарная безопасность'], 'is_required' => true],
            
            // Для инженера
            ['position_id' => $positions['Инженер'], 'course_id' => $courses['Охрана труда'], 'is_required' => true],
            ['position_id' => $positions['Инженер'], 'course_id' => $courses['Управление проектами'], 'is_required' => false],
            ['position_id' => $positions['Инженер'], 'course_id' => $courses['Экологическая безопасность'], 'is_required' => true],
            
            // Для всех руководящих должностей
            ['position_id' => $positions['Начальник участка'], 'course_id' => $courses['Управление проектами'], 'is_required' => true],
            ['position_id' => $positions['Прораб'], 'course_id' => $courses['Управление проектами'], 'is_required' => true],
        ];
        
        foreach ($requirements as $requirement) {
            DB::table('position_course_requirements')->insert(array_merge($requirement, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}
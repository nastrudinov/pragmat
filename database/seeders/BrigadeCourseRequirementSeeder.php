<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrigadeCourseRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $brigades = DB::table('brigades')->pluck('id', 'name')->toArray();
        $courses = DB::table('courses')->pluck('id', 'name')->toArray();
        
        $requirements = [
            // Бригада №1 (Электромонтажная)
            ['brigade_id' => $brigades['Бригада №1 (Электромонтажная)'], 'course_id' => $courses['Электробезопасность'], 'is_required' => true],
            ['brigade_id' => $brigades['Бригада №1 (Электромонтажная)'], 'course_id' => $courses['Работа на высоте'], 'is_required' => true],
            
            // Бригада №3 (Сварочная)
            ['brigade_id' => $brigades['Бригада №3 (Сварочная)'], 'course_id' => $courses['Сварочные работы'], 'is_required' => true],
            
            // Аварийная бригада
            ['brigade_id' => $brigades['Аварийная бригада'], 'course_id' => $courses['Первая помощь'], 'is_required' => true],
        ];
        
        foreach ($requirements as $requirement) {
            DB::table('brigade_course_requirements')->insert(array_merge($requirement, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}
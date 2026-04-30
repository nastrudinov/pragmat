<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Справочники
        $this->call(PositionCategorySeeder::class);
        $this->call(PositionSeeder::class);
        $this->call(BrigadeSeeder::class);
        $this->call(EmployeeSeeder::class);
        $this->call(CourseCategorySeeder::class);
        $this->call(CourseSeeder::class);
        // Создаем подразделения
        $this->call(DepartmentSeeder::class);
        
        // Связи компетенций
        $this->call(PositionCourseRequirementSeeder::class);
        $this->call(BrigadeCourseRequirementSeeder::class);
        $this->call(EmployeeCourseSeeder::class);
        
        // Мобилизации
        $this->call(MobilizationStageSeeder::class);
        $this->call(MobilizationSeeder::class);
        $this->call(MobilizationEmployeeSeeder::class);
        $this->call(StageHistorySeeder::class);
        
        // Пользователи и уведомления
        $this->call(UserAccountSeeder::class);
        $this->call(NotificationSeeder::class);
    }
}
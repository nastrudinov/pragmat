<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('course_categories')->insert([
            ['name' => 'Минимально-Обязательные', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Технические', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Дополнительные', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Проекты', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Безопасность', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
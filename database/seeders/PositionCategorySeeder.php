<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('position_categories')->insert([
            ['name' => 'Рабочие специальности', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ИТР', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Руководящий состав', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Административный персонал', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Обслуживающий персонал', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
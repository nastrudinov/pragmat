<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $categories = DB::table('position_categories')->pluck('id', 'name')->toArray();
        
        $positions = [
            // Рабочие специальности
            ['name' => 'Электромонтер', 'category_id' => $categories['Рабочие специальности']],
            ['name' => 'Слесарь', 'category_id' => $categories['Рабочие специальности']],
            ['name' => 'Сварщик', 'category_id' => $categories['Рабочие специальности']],
            ['name' => 'Машинист', 'category_id' => $categories['Рабочие специальности']],
            ['name' => 'Стропальщик', 'category_id' => $categories['Рабочие специальности']],
            
            // ИТР
            ['name' => 'Инженер', 'category_id' => $categories['ИТР']],
            ['name' => 'Технолог', 'category_id' => $categories['ИТР']],
            ['name' => 'Конструктор', 'category_id' => $categories['ИТР']],
            ['name' => 'Программист', 'category_id' => $categories['ИТР']],
            ['name' => 'Энергетик', 'category_id' => $categories['ИТР']],
            
            // Руководящий состав
            ['name' => 'Начальник участка', 'category_id' => $categories['Руководящий состав']],
            ['name' => 'Прораб', 'category_id' => $categories['Руководящий состав']],
            ['name' => 'Мастер', 'category_id' => $categories['Руководящий состав']],
            ['name' => 'Директор', 'category_id' => $categories['Руководящий состав']],
            ['name' => 'Главный инженер', 'category_id' => $categories['Руководящий состав']],
            
            // Административный персонал
            ['name' => 'Бухгалтер', 'category_id' => $categories['Административный персонал']],
            ['name' => 'Секретарь', 'category_id' => $categories['Административный персонал']],
            ['name' => 'Кадровик', 'category_id' => $categories['Административный персонал']],
            ['name' => 'Экономист', 'category_id' => $categories['Административный персонал']],
            ['name' => 'Юрист', 'category_id' => $categories['Административный персонал']],
        ];
        
        foreach ($positions as $position) {
            DB::table('positions')->insert(array_merge($position, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}
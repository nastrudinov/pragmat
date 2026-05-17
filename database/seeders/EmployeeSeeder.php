<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $positions = DB::table('positions')->pluck('id', 'name')->toArray();
        $brigades = DB::table('brigades')->pluck('id', 'name')->toArray();
        
        $employees = [
            [
                'full_name' => 'Иванов Иван Иванович',
                'last_name' => 'Иванов',
                'first_name' => 'Иван',
                'middle_name' => 'Иванович',
                'position_id' => $positions['Директор'],
                'brigade_id' => null,
                'email' => 'ivanov@company.ru',
                'phone' => '+7-999-111-1111',
                'status' => 'active'
            ],
            [
                'full_name' => 'Петров Петр Петрович',
                'last_name' => 'Петров',
                'first_name' => 'Петр',
                'middle_name' => 'Петрович',
                'position_id' => $positions['Главный инженер'],
                'brigade_id' => null,
                'email' => 'petrov@company.ru',
                'phone' => '+7-999-222-2222',
                'status' => 'active'
            ],
            [
                'full_name' => 'Сидоров Алексей Владимирович',
                'last_name' => 'Сидоров',
                'first_name' => 'Алексей',
                'middle_name' => 'Владимирович',
                'position_id' => $positions['Начальник участка'],
                'brigade_id' => $brigades['Бригада №1 (Электромонтажная)'],
                'email' => 'sidorov@company.ru',
                'phone' => '+7-999-333-3333',
                'status' => 'active'
            ],
            [
                'full_name' => 'Кузнецов Дмитрий Сергеевич',
                'last_name' => 'Кузнецов',
                'first_name' => 'Дмитрий',
                'middle_name' => 'Сергеевич',
                'position_id' => $positions['Электромонтер'],
                'brigade_id' => $brigades['Бригада №1 (Электромонтажная)'],
                'email' => 'kuznetsov@company.ru',
                'phone' => '+7-999-444-4444',
                'status' => 'active'
            ],
            [
                'full_name' => 'Волков Андрей Михайлович',
                'last_name' => 'Волков',
                'first_name' => 'Андрей',
                'middle_name' => 'Михайлович',
                'position_id' => $positions['Электромонтер'],
                'brigade_id' => $brigades['Бригада №1 (Электромонтажная)'],
                'email' => 'volkov@company.ru',
                'phone' => '+7-999-555-5555',
                'status' => 'active'
            ],
            [
                'full_name' => 'Морозов Сергей Александрович',
                'last_name' => 'Морозов',
                'first_name' => 'Сергей',
                'middle_name' => 'Александрович',
                'position_id' => $positions['Слесарь'],
                'brigade_id' => $brigades['Бригада №2 (Сантехническая)'],
                'email' => 'morozov@company.ru',
                'phone' => '+7-999-666-6666',
                'status' => 'active'
            ],
            [
                'full_name' => 'Новикова Елена Владимировна',
                'last_name' => 'Новикова',
                'first_name' => 'Елена',
                'middle_name' => 'Владимировна',
                'position_id' => $positions['Бухгалтер'],
                'brigade_id' => null,
                'email' => 'novikova@company.ru',
                'phone' => '+7-999-777-7777',
                'status' => 'active'
            ],
            [
                'full_name' => 'Соколов Олег Иванович',
                'last_name' => 'Соколов',
                'first_name' => 'Олег',
                'middle_name' => 'Иванович',
                'position_id' => $positions['Сварщик'],
                'brigade_id' => $brigades['Бригада №3 (Сварочная)'],
                'email' => 'sokolov@company.ru',
                'phone' => '+7-999-888-8888',
                'status' => 'active'
            ],
            [
                'full_name' => 'Михайлов Артем Дмитриевич',
                'last_name' => 'Михайлов',
                'first_name' => 'Артем',
                'middle_name' => 'Дмитриевич',
                'position_id' => $positions['Инженер'],
                'brigade_id' => null,
                'email' => 'mikhailov@company.ru',
                'phone' => '+7-999-999-9999',
                'status' => 'active'
            ],
            [
                'full_name' => 'Федорова Анна Андреевна',
                'last_name' => 'Федорова',
                'first_name' => 'Анна',
                'middle_name' => 'Андреевна',
                'position_id' => $positions['Кадровик'],
                'brigade_id' => null,
                'email' => 'fedorova@company.ru',
                'phone' => '+7-999-000-0000',
                'status' => 'active'
            ],
        ];
        
        foreach ($employees as $employee) {
            DB::table('employees')->insert(array_merge($employee, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
        
        // Назначаем руководителей бригад
        $brigadesList = DB::table('brigades')->get();
        $employeesList = DB::table('employees')->get();
        
        foreach ($brigadesList as $brigade) {
            if ($brigade->name === 'Бригада №1 (Электромонтажная)') {
                $leader = $employeesList->where('full_name', 'Сидоров Алексей Владимирович')->first();
                if ($leader) {
                    DB::table('brigades')->where('id', $brigade->id)->update(['leader_employee_id' => $leader->id]);
                }
            } elseif ($brigade->name === 'Бригада №3 (Сварочная)') {
                $leader = $employeesList->where('full_name', 'Соколов Олег Иванович')->first();
                if ($leader) {
                    DB::table('brigades')->where('id', $brigade->id)->update(['leader_employee_id' => $leader->id]);
                }
            }
        }
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAccountSeeder extends Seeder
{
    public function run(): void
    {
        $employees = DB::table('employees')->pluck('id', 'email')->toArray();
        
        $users = [
            [
                'employee_id' => $employees['ivanov@company.ru'] ?? null,
                'username' => 'ivanov',
                'password_hash' => Hash::make('password123'),
                'role' => 'admin',
                'status' => 'active',
                'last_login' => now(),
            ],
            [
                'employee_id' => $employees['petrov@company.ru'] ?? null,
                'username' => 'petrov',
                'password_hash' => Hash::make('password123'),
                'role' => 'hr_manager',
                'status' => 'active',
                'last_login' => now()->subDays(2),
            ],
            [
                'employee_id' => $employees['sidorov@company.ru'] ?? null,
                'username' => 'sidorov',
                'password_hash' => Hash::make('password123'),
                'role' => 'training_curator',
                'status' => 'active',
                'last_login' => now()->subDays(5),
            ],
            [
                'employee_id' => $employees['kuznetsov@company.ru'] ?? null,
                'username' => 'kuznetsov',
                'password_hash' => Hash::make('password123'),
                'role' => 'user',
                'status' => 'active',
                'last_login' => now()->subDays(3),
            ],
            [
                'employee_id' => $employees['fedorova@company.ru'] ?? null,
                'username' => 'fedorova',
                'password_hash' => Hash::make('password123'),
                'role' => 'hr_manager',
                'status' => 'active',
                'last_login' => null,
            ],
            // Добавляем пользователя для Волкова
            [
                'employee_id' => $employees['volkov@company.ru'] ?? null,
                'username' => 'volkov',
                'password_hash' => Hash::make('password123'),
                'role' => 'user',
                'status' => 'active',
                'last_login' => null,
            ],
        ];
        
        foreach ($users as $user) {
            if ($user['employee_id']) {
                DB::table('user_accounts')->insert(array_merge($user, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }
    }
}
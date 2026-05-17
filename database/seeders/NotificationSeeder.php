<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        // Получаем всех пользователей с их username
        $userAccounts = DB::table('user_accounts')
            ->select('id', 'username')
            ->get();
        
        // Создаем карту username => id
        $userMap = [];
        foreach ($userAccounts as $account) {
            $userMap[$account->username] = $account->id;
        }
        
        $notifications = [];
        
        // Уведомления для kuznetsov
        if (isset($userMap['kuznetsov'])) {
            $notifications[] = [
                'user_account_id' => $userMap['kuznetsov'],
                'title' => 'Истечение срока сертификации',
                'message' => 'У вас истекает срок действия сертификации по электробезопасности. Пройдите переобучение до ' . Carbon::now()->addMonth()->format('d.m.Y'),
                'type' => 'expiring_alert',
                'is_read' => false,
                'read_at' => null,
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2)
            ];
            
            $notifications[] = [
                'user_account_id' => $userMap['kuznetsov'],
                'title' => 'Назначение на проект',
                'message' => 'Вы назначены ответственным исполнителем на проекте "Модернизация оборудования на ТЭЦ-1"',
                'type' => 'task_assigned',
                'is_read' => true,
                'read_at' => Carbon::now()->subDays(1),
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(1)
            ];
        }
        
        // Уведомления для volkov (если есть учетная запись)
        if (isset($userMap['volkov'])) {
            $notifications[] = [
                'user_account_id' => $userMap['volkov'],
                'title' => 'Просроченное обучение',
                'message' => 'У вас просрочен курс по электробезопасности. Необходимо срочно пройти переобучение!',
                'type' => 'expiring_alert',
                'is_read' => false,
                'read_at' => null,
                'created_at' => Carbon::now()->subDays(10),
                'updated_at' => Carbon::now()->subDays(10)
            ];
        }
        
        // Уведомления для fedorova
        if (isset($userMap['fedorova'])) {
            $notifications[] = [
                'user_account_id' => $userMap['fedorova'],
                'title' => 'Новое назначение',
                'message' => 'Для сотрудника Федоровой Анны Андреевны назначен обязательный курс "Охрана труда"',
                'type' => 'task_assigned',
                'is_read' => false,
                'read_at' => null,
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(3)
            ];
        }
        
        // Уведомления для sidorov
        if (isset($userMap['sidorov'])) {
            $notifications[] = [
                'user_account_id' => $userMap['sidorov'],
                'title' => 'Завершение этапа мобилизации',
                'message' => 'Этап "Формирование бригады" на проекте "Ремонтные работы на подстанции 220кВ" требует вашего внимания',
                'type' => 'task_assigned',
                'is_read' => false,
                'read_at' => null,
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now()->subDay()
            ];
        }
        
        // Уведомления для ivanov
        if (isset($userMap['ivanov'])) {
            $notifications[] = [
                'user_account_id' => $userMap['ivanov'],
                'title' => 'Новый проект',
                'message' => 'Создан новый проект "Модернизация оборудования на ТЭЦ-1"',
                'type' => 'task_assigned',
                'is_read' => false,
                'read_at' => null,
                'created_at' => Carbon::now()->subDays(7),
                'updated_at' => Carbon::now()->subDays(7)
            ];
        }
        
        // Уведомления для petrov
        if (isset($userMap['petrov'])) {
            $notifications[] = [
                'user_account_id' => $userMap['petrov'],
                'title' => 'Отчет по обучению',
                'message' => 'Сформирован отчет по обучениям сотрудников за текущий месяц',
                'type' => 'report_ready',
                'is_read' => false,
                'read_at' => null,
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1)
            ];
        }
        
        // Вставляем все уведомления
        foreach ($notifications as $notification) {
            DB::table('notifications')->insert($notification);
        }
    }
}
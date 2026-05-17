<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeCourseSeeder extends Seeder
{
    public function run(): void
    {
        $employees = DB::table('employees')->pluck('id', 'full_name')->toArray();
        $courses = DB::table('courses')->pluck('id', 'name')->toArray();
        
        $employeeCourses = [
            // Для Иванова (директор)
            [
                'employee_id' => $employees['Иванов Иван Иванович'],
                'course_id' => $courses['Охрана труда'],
                'status' => 'active',
                'assigned_date' => Carbon::now()->subMonths(6),
                'completed_date' => Carbon::now()->subMonths(6),
                'expiration_date' => Carbon::now()->addMonths(6),
                'certificate_file_path' => '/certificates/ivanov_ohrana_truda.pdf'
            ],
            [
                'employee_id' => $employees['Иванов Иван Иванович'],
                'course_id' => $courses['Управление проектами'],
                'status' => 'active',
                'assigned_date' => Carbon::now()->subMonths(3),
                'completed_date' => Carbon::now()->subMonths(3),
                'expiration_date' => Carbon::now()->addMonths(21),
                'certificate_file_path' => '/certificates/ivanov_upravlenie_proektami.pdf'
            ],
            
            // Для Кузнецова (электромонтер)
            [
                'employee_id' => $employees['Кузнецов Дмитрий Сергеевич'],
                'course_id' => $courses['Электробезопасность'],
                'status' => 'expiring',
                'assigned_date' => Carbon::now()->subMonths(11),
                'completed_date' => Carbon::now()->subMonths(11),
                'expiration_date' => Carbon::now()->addMonths(1),
                'certificate_file_path' => '/certificates/kuznetsov_elektrobezopasnost.pdf',
                'last_reminder_sent' => Carbon::now()->subDays(15)
            ],
            [
                'employee_id' => $employees['Кузнецов Дмитрий Сергеевич'],
                'course_id' => $courses['Работа на высоте'],
                'status' => 'active',
                'assigned_date' => Carbon::now()->subMonths(8),
                'completed_date' => Carbon::now()->subMonths(8),
                'expiration_date' => Carbon::now()->addMonths(4),
                'certificate_file_path' => '/certificates/kuznetsov_vysota.pdf'
            ],
            
            // Для Волкова (электромонтер)
            [
                'employee_id' => $employees['Волков Андрей Михайлович'],
                'course_id' => $courses['Электробезопасность'],
                'status' => 'expired',
                'assigned_date' => Carbon::now()->subMonths(14),
                'completed_date' => Carbon::now()->subMonths(14),
                'expiration_date' => Carbon::now()->subMonths(2),
                'certificate_file_path' => '/certificates/volkov_elektrobezopasnost.pdf'
            ],
            
            // Для Соколова (сварщик)
            [
                'employee_id' => $employees['Соколов Олег Иванович'],
                'course_id' => $courses['Сварочные работы'],
                'status' => 'active',
                'assigned_date' => Carbon::now()->subMonths(5),
                'completed_date' => Carbon::now()->subMonths(5),
                'expiration_date' => Carbon::now()->addMonths(7),
                'certificate_file_path' => '/certificates/sokolov_svarka.pdf'
            ],
            
            // Для Федоровой (кадровик)
            [
                'employee_id' => $employees['Федорова Анна Андреевна'],
                'course_id' => $courses['Охрана труда'],
                'status' => 'required',
                'assigned_date' => Carbon::now()->subDays(5),
                'completed_date' => null,
                'expiration_date' => null,
                'certificate_file_path' => null
            ],
        ];
        
        foreach ($employeeCourses as $course) {
            DB::table('employee_courses')->insert(array_merge($course, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}
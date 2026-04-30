<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Employee;

class DepartmentSeeder extends Seeder
{
    public function run()
    {
        // Очищаем таблицу перед заполнением (опционально)
        //Department::truncate();

        // Создаем корневые подразделения (1-й уровень)
        $departments = [
            [
                'name' => 'Производственный департамент',
                'code' => 'PROD',
                'parent_id' => null,
                'head_employee_id' => null, // ID руководителя, можно позже обновить
                'phone' => '+7 (495) 111-22-33',
                'email' => 'prod@company.ru',
                'description' => 'Основное производственное подразделение компании',
                'sort_order' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Департамент безопасности',
                'code' => 'SAFE',
                'parent_id' => null,
                'head_employee_id' => null,
                'phone' => '+7 (495) 222-33-44',
                'email' => 'safety@company.ru',
                'description' => 'Обеспечение промышленной и пожарной безопасности',
                'sort_order' => 2,
                'status' => 'active'
            ],
            [
                'name' => 'Инженерно-технический департамент',
                'code' => 'ENG',
                'parent_id' => null,
                'head_employee_id' => null,
                'phone' => '+7 (495) 333-44-55',
                'email' => 'engineering@company.ru',
                'description' => 'Инженерное сопровождение и техническая поддержка',
                'sort_order' => 3,
                'status' => 'active'
            ],
            [
                'name' => 'Департамент обучения и развития',
                'code' => 'HRD',
                'parent_id' => null,
                'head_employee_id' => null,
                'phone' => '+7 (495) 444-55-66',
                'email' => 'training@company.ru',
                'description' => 'Обучение персонала и развитие компетенций',
                'sort_order' => 4,
                'status' => 'active'
            ],
            [
                'name' => 'Административный департамент',
                'code' => 'ADMIN',
                'parent_id' => null,
                'head_employee_id' => null,
                'phone' => '+7 (495) 555-66-77',
                'email' => 'admin@company.ru',
                'description' => 'Административно-хозяйственное обеспечение',
                'sort_order' => 5,
                'status' => 'active'
            ]
        ];

        // Создаем подразделения 1-го уровня
        $createdDepartments = [];
        foreach ($departments as $deptData) {
            $department = Department::create($deptData);
            $createdDepartments[$department->code] = $department;
        }

        // Создаем дочерние подразделения (2-й уровень)
        $childDepartments = [
            // Дочерние для Производственного департамента
            [
                'name' => 'Цех №1 - Механическая обработка',
                'code' => 'PROD-WS1',
                'parent_id' => $createdDepartments['PROD']->id,
                'phone' => '+7 (495) 111-22-34',
                'email' => 'workshop1@company.ru',
                'description' => 'Механическая обработка деталей',
                'sort_order' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Цех №2 - Сборочный цех',
                'code' => 'PROD-WS2',
                'parent_id' => $createdDepartments['PROD']->id,
                'phone' => '+7 (495) 111-22-35',
                'email' => 'workshop2@company.ru',
                'description' => 'Сборка оборудования',
                'sort_order' => 2,
                'status' => 'active'
            ],
            [
                'name' => 'Отдел контроля качества',
                'code' => 'PROD-QC',
                'parent_id' => $createdDepartments['PROD']->id,
                'phone' => '+7 (495) 111-22-36',
                'email' => 'quality@company.ru',
                'description' => 'Контроль качества продукции',
                'sort_order' => 3,
                'status' => 'active'
            ],
            
            // Дочерние для Департамента безопасности
            [
                'name' => 'Отдел охраны труда',
                'code' => 'SAFE-OSH',
                'parent_id' => $createdDepartments['SAFE']->id,
                'phone' => '+7 (495) 222-33-45',
                'email' => 'ohrana@company.ru',
                'description' => 'Охрана труда и техника безопасности',
                'sort_order' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Отдел пожарной безопасности',
                'code' => 'SAFE-FIRE',
                'parent_id' => $createdDepartments['SAFE']->id,
                'phone' => '+7 (495) 222-33-46',
                'email' => 'fire@company.ru',
                'description' => 'Пожарная безопасность',
                'sort_order' => 2,
                'status' => 'active'
            ],
            [
                'name' => 'Отдел промышленной безопасности',
                'code' => 'SAFE-IND',
                'parent_id' => $createdDepartments['SAFE']->id,
                'phone' => '+7 (495) 222-33-47',
                'email' => 'industrial@company.ru',
                'description' => 'Промышленная безопасность',
                'sort_order' => 3,
                'status' => 'active'
            ],
            
            // Дочерние для Инженерно-технического департамента
            [
                'name' => 'Конструкторский отдел',
                'code' => 'ENG-DESIGN',
                'parent_id' => $createdDepartments['ENG']->id,
                'phone' => '+7 (495) 333-44-56',
                'email' => 'design@company.ru',
                'description' => 'Проектирование и разработка',
                'sort_order' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Отдел главного механика',
                'code' => 'ENG-MECH',
                'parent_id' => $createdDepartments['ENG']->id,
                'phone' => '+7 (495) 333-44-57',
                'email' => 'mech@company.ru',
                'description' => 'Обслуживание оборудования',
                'sort_order' => 2,
                'status' => 'active'
            ],
            [
                'name' => 'IT-отдел',
                'code' => 'ENG-IT',
                'parent_id' => $createdDepartments['ENG']->id,
                'phone' => '+7 (495) 333-44-58',
                'email' => 'it@company.ru',
                'description' => 'Информационные технологии',
                'sort_order' => 3,
                'status' => 'active'
            ],
            
            // Дочерние для Департамента обучения и развития
            [
                'name' => 'Отдел технического обучения',
                'code' => 'HRD-TECH',
                'parent_id' => $createdDepartments['HRD']->id,
                'phone' => '+7 (495) 444-55-67',
                'email' => 'tech.training@company.ru',
                'description' => 'Техническое обучение персонала',
                'sort_order' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Отдел оценки персонала',
                'code' => 'HRD-ASSESS',
                'parent_id' => $createdDepartments['HRD']->id,
                'phone' => '+7 (495) 444-55-68',
                'email' => 'assessment@company.ru',
                'description' => 'Оценка компетенций',
                'sort_order' => 2,
                'status' => 'active'
            ],
            [
                'name' => 'Учебный центр',
                'code' => 'HRD-CENTER',
                'parent_id' => $createdDepartments['HRD']->id,
                'phone' => '+7 (495) 444-55-69',
                'email' => 'training.center@company.ru',
                'description' => 'Проведение обучающих программ',
                'sort_order' => 3,
                'status' => 'active'
            ],
            
            // Дочерние для Административного департамента
            [
                'name' => 'Отдел кадров',
                'code' => 'ADMIN-HR',
                'parent_id' => $createdDepartments['ADMIN']->id,
                'phone' => '+7 (495) 555-66-78',
                'email' => 'hr@company.ru',
                'description' => 'Управление персоналом',
                'sort_order' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Юридический отдел',
                'code' => 'ADMIN-LEGAL',
                'parent_id' => $createdDepartments['ADMIN']->id,
                'phone' => '+7 (495) 555-66-79',
                'email' => 'legal@company.ru',
                'description' => 'Правовое обеспечение',
                'sort_order' => 2,
                'status' => 'active'
            ],
            [
                'name' => 'Бухгалтерия',
                'code' => 'ADMIN-ACC',
                'parent_id' => $createdDepartments['ADMIN']->id,
                'phone' => '+7 (495) 555-66-80',
                'email' => 'accounting@company.ru',
                'description' => 'Бухгалтерский учет',
                'sort_order' => 3,
                'status' => 'active'
            ]
        ];

        // Создаем дочерние подразделения
        foreach ($childDepartments as $childData) {
            Department::create($childData);
        }

        // Обновляем руководителей подразделений (если есть сотрудники)
        $this->assignDepartmentHeads();
    }

    /**
     * Назначить руководителей подразделений
     * Выполняется после создания сотрудников
     */
    private function assignDepartmentHeads()
    {
        // Ищем сотрудников для назначения руководителями
        $prodHead = Employee::where('position_id', function($query) {
            $query->select('id')->from('positions')->where('name', 'LIKE', '%Начальник%');
        })->first();
        
        if ($prodHead) {
            Department::where('code', 'PROD')->update(['head_employee_id' => $prodHead->id]);
        }
        
        $safetyHead = Employee::where('position_id', function($query) {
            $query->select('id')->from('positions')->where('name', 'LIKE', '%Безопасность%');
        })->first();
        
        if ($safetyHead) {
            Department::where('code', 'SAFE')->update(['head_employee_id' => $safetyHead->id]);
        }
    }
}
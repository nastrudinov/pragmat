<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Brigade;
use App\Models\Position;
use App\Models\EmployeeCourse;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    /**
     * 4.1 GET /employees - Список всех сотрудников (без пагинации)
     */
    public function index(Request $request)
    {
        try {
            $query = Employee::with(['position', 'brigade', 'department'])
                ->select('employees.*');
            
            // Фильтр по статусу
            if ($request->has('status')) {
                $query->where('employees.status', $request->status);
            }
            
            // Фильтр по бригаде
            if ($request->has('brigade_id')) {
                $query->where('brigade_id', $request->brigade_id);
            }
            
            // Фильтр по должности
            if ($request->has('position_id')) {
                $query->where('position_id', $request->position_id);
            }
            
            // Фильтр по подразделению
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            
            // Поиск
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('personnel_number', 'LIKE', "%{$search}%")
                    ->orWhere('full_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }
            
            // Сортировка по personnel_number (по умолчанию)
            $sortField = $request->get('sort_by', 'personnel_number');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            // Разрешенные поля для сортировки
            $allowedSortFields = ['personnel_number', 'full_name', 'last_name', 'created_at', 'status'];
            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'personnel_number';
            }
            
            $query->orderBy($sortField, $sortDirection);
            
            // Получаем всех сотрудников (без пагинации)
            $employees = $query->get();
            
            $formattedEmployees = $employees->map(function($employee) {
                // Формируем ФИО из трех полей
                $fullName = trim(implode(' ', array_filter([
                    $employee->last_name,
                    $employee->first_name,
                    $employee->middle_name
                ])));
                
                // Если ФИО пустое, используем full_name из БД как запасной вариант
                if (empty($fullName)) {
                    $fullName = $employee->full_name;
                }
                
                return [
                    'id' => $employee->id,
                    'personnel_number' => $employee->personnel_number,
                    'full_name' => $fullName,
                    'last_name' => $employee->last_name,
                    'first_name' => $employee->first_name,
                    'middle_name' => $employee->middle_name,
                    'position' => $employee->position?->name ?? 'Не указана',
                    'position_id' => $employee->position_id,
                    'department' => $employee->department?->name ?? 'Не указано',
                    'department_id' => $employee->department_id,
                    'brigade' => $employee->brigade?->name ?? 'Не указана',
                    'brigade_id' => $employee->brigade_id,
                    'status' => $employee->status,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'created_at' => $employee->created_at?->toISOString()
                ];
            });
            
            return response()->json([
                'employees' => $formattedEmployees,
                'total' => $employees->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch employees',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 4.2 GET /employees/{id} - Детали сотрудника
     */
    public function show($id)
    {
        try {
            $employee = Employee::with(['position', 'brigade', 'department', 'userAccount'])
                ->findOrFail($id);
            
            // Формируем ФИО из трех полей
            $fullName = trim(implode(' ', array_filter([
                $employee->last_name,
                $employee->first_name,
                $employee->middle_name
            ])));
            
            if (empty($fullName)) {
                $fullName = $employee->full_name;
            }
            
            // Получаем обучения сотрудника
            $trainings = EmployeeCourse::with('course')
                ->where('employee_id', $id)
                ->get()
                ->map(function($training) {
                    return [
                        'course_id' => $training->course_id,
                        'course_name' => $training->course?->name ?? 'Неизвестный курс',
                        'status' => $training->status,
                        'assigned_date' => $training->assigned_date?->format('Y-m-d'),
                        'completed_date' => $training->completed_date?->format('Y-m-d'),
                        'expiration_date' => $training->expiration_date?->format('Y-m-d'),
                        'certificate' => $training->certificate_file_path
                    ];
                });
            
            return response()->json([
                'id' => $employee->id,
                'personnel_number' => $employee->personnel_number,  // Добавлено
                'full_name' => $fullName,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'position' => $employee->position?->name ?? 'Не указана',
                'position_id' => $employee->position_id,
                'department' => $employee->department?->name ?? 'Не указано',
                'department_id' => $employee->department_id,
                'brigade' => $employee->brigade?->name ?? 'Не указана',
                'brigade_id' => $employee->brigade_id,
                'status' => $employee->status,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'has_account' => !is_null($employee->userAccount),
                'trainings' => $trainings,
                'created_at' => $employee->created_at?->toISOString(),
                'updated_at' => $employee->updated_at?->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Employee not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch employee details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 4.3 GET /employees/brigade/{brigadeId} - Сотрудники бригады
     */
    public function getByBrigade($brigadeId)
    {
        try {
            $brigade = Brigade::findOrFail($brigadeId);
            
            $employees = Employee::with('position')
                ->where('brigade_id', $brigadeId)
                ->where('status', 'active')
                ->get()
                ->map(function($employee) {
                    $fullName = trim(implode(' ', array_filter([
                        $employee->last_name,
                        $employee->first_name,
                        $employee->middle_name
                    ])));
                    
                    if (empty($fullName)) {
                        $fullName = $employee->full_name;
                    }
                    
                    return [
                        'id' => $employee->id,
                        'name' => $fullName,
                        'position' => $employee->position?->name ?? 'Не указана',
                        'status' => $employee->status
                    ];
                });
            
            return response()->json([
                'brigadeId' => (int)$brigadeId,
                'brigadeName' => $brigade->name,
                'employees' => $employees
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Brigade not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch brigade employees',
                'message' => $e->getMessage()
            ], 500);
        }
    }
        
    /**
     * 4.4 GET /employees/search - Поиск сотрудников
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('query', '');
            $limit = $request->get('limit', 50);
            
            if (empty($query)) {
                return response()->json([
                    'employees' => [],
                    'total' => 0
                ], 200);
            }
            
            $employees = Employee::with(['position', 'brigade', 'department'])
                ->where(function($q) use ($query) {
                    $q->where('full_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_name', 'LIKE', "%{$query}%")
                    ->orWhere('first_name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get()
                ->map(function($employee) {
                    $fullName = trim(implode(' ', array_filter([
                        $employee->last_name,
                        $employee->first_name,
                        $employee->middle_name
                    ])));
                    
                    if (empty($fullName)) {
                        $fullName = $employee->full_name;
                    }
                    
                    return [
                        'id' => $employee->id,
                        'name' => $fullName,
                        'position' => $employee->position?->name ?? 'Не указана',
                        'department' => $employee->department?->name ?? 'Не указано',
                        'brigade' => $employee->brigade?->name ?? 'Не указана'
                    ];
                });
            
            return response()->json([
                'employees' => $employees,
                'total' => $employees->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 4.5 GET /employees/statistics - Статистика по персоналу
     */
    public function getStatistics()
    {
        try {
            $total = Employee::count();
            
            // Расчет соответствующих требованиям сотрудников
            // Сотрудник НЕ соответствует, если у него есть хотя бы один курс со статусом 'expired'
            $nonCompliant = Employee::whereHas('employeeCourses', function($query) {
                $query->where('status', 'expired');
            })->count();
            
            $compliant = $total - $nonCompliant;
            $compliancePercentage = $total > 0 ? round(($compliant / $total) * 100) : 0;
            
            // Статистика по должностям
            $byPosition = [];
            
            // Получаем все должности с сотрудниками
            $positions = Position::with(['employees.employeeCourses' => function($query) {
                $query->where('status', 'expired');
            }])->get();
            
            foreach ($positions as $position) {
                $positionTotal = $position->employees->count();
                
                if ($positionTotal > 0) {
                    // Считаем количество несоответствующих сотрудников для этой должности
                    $positionNonCompliant = $position->employees->filter(function($employee) {
                        return $employee->employeeCourses->where('status', 'expired')->count() > 0;
                    })->count();
                    
                    $byPosition[$position->name] = [
                        'total' => $positionTotal,
                        'compliant' => $positionTotal - $positionNonCompliant
                    ];
                }
            }
            
            return response()->json([
                'total' => $total,
                'compliant' => $compliant,
                'nonCompliant' => $nonCompliant,
                'compliancePercentage' => $compliancePercentage,
                'byPosition' => $byPosition
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Statistics error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
        
    /**
     * 4.6 GET /employees/compliance - Данные о соответствии
     */
    public function getCompliance()
    {
        try {
            $total = Employee::count();
            
            // Соответствующие сотрудники (без просроченных обучений)
            $compliant = Employee::whereDoesntHave('employeeCourses', function($query) {
                $query->where('status', 'expired');
            })->count();
            
            $percentage = $total > 0 ? round(($compliant / $total) * 100) : 0;
            
            // Статистика по категориям курсов
            $categories = DB::table('course_categories')
                ->leftJoin('courses', 'course_categories.id', '=', 'courses.category_id')
                ->leftJoin('employee_courses', 'courses.id', '=', 'employee_courses.course_id')
                ->select(
                    'course_categories.name',
                    DB::raw('COUNT(DISTINCT employee_courses.employee_id) as total_employees'),
                    DB::raw('SUM(CASE WHEN employee_courses.status = "expired" THEN 1 ELSE 0 END) as non_compliant_count')
                )
                ->whereNotNull('employee_courses.employee_id')
                ->groupBy('course_categories.id', 'course_categories.name')
                ->get();
            
            $categoriesData = $categories->map(function($category) {
                $totalEmployees = (int)$category->total_employees;
                $nonCompliant = (int)$category->non_compliant_count;
                $compliantCount = $totalEmployees - $nonCompliant;
                $percentage = $totalEmployees > 0 
                    ? round(($compliantCount / $totalEmployees) * 100) 
                    : 0;
                
                return [
                    'name' => $category->name,
                    'count' => $totalEmployees,
                    'percentage' => $percentage
                ];
            });
            
            // Если нет данных по категориям, возвращаем пустой массив
            if ($categoriesData->isEmpty()) {
                return response()->json([
                    'total' => $total,
                    'compliant' => $compliant,
                    'percentage' => $percentage,
                    'categories' => []
                ], 200);
            }
            
            return response()->json([
                'total' => $total,
                'compliant' => $compliant,
                'percentage' => $percentage,
                'categories' => $categoriesData
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Compliance error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch compliance data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 4.7 POST /employees - Создание сотрудника
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'personnel_number' => 'nullable|string|max:20|unique:employees,personnel_number',  // Добавлено
                'last_name' => 'required|string|max:50',
                'first_name' => 'required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'position_id' => 'nullable|exists:positions,id',
                'brigade_id' => 'nullable|exists:brigades,id',
                'department_id' => 'nullable|exists:departments,id',
                'email' => 'nullable|email|max:100|unique:employees,email',
                'phone' => 'nullable|string|max:20',
                'status' => 'in:active,inactive'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Собираем full_name из трех полей
            $fullName = trim(implode(' ', array_filter([
                $request->last_name,
                $request->first_name,
                $request->middle_name
            ])));
            
            $employee = Employee::create([
                'personnel_number' => $request->personnel_number,  // Добавлено
                'full_name' => $fullName,
                'last_name' => $request->last_name,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'position_id' => $request->position_id,
                'brigade_id' => $request->brigade_id,
                'department_id' => $request->department_id,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => $request->get('status', 'active')
            ]);
            
            $employee->load(['position', 'brigade', 'department']);
            
            // Формируем ответ с полным ФИО
            $responseFullName = trim(implode(' ', array_filter([
                $employee->last_name,
                $employee->first_name,
                $employee->middle_name
            ])));
            
            return response()->json([
                'id' => $employee->id,
                'personnel_number' => $employee->personnel_number,  // Добавлено
                'full_name' => $responseFullName,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'position' => $employee->position?->name ?? 'Не указана',
                'position_id' => $employee->position_id,
                'department' => $employee->department?->name ?? 'Не указано',
                'department_id' => $employee->department_id,
                'brigade' => $employee->brigade?->name ?? 'Не указана',
                'brigade_id' => $employee->brigade_id,
                'status' => $employee->status,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'createdAt' => $employee->created_at->toISOString()
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create employee',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 4.8 PUT /employees/{id} - Обновление сотрудника
     */
    public function update(Request $request, $id)
    {
        try {
            $employee = Employee::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'personnel_number' => 'nullable|string|max:20|unique:employees,personnel_number,' . $id,  // Добавлено
                'last_name' => 'nullable|string|max:50',
                'first_name' => 'nullable|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'position_id' => 'nullable|exists:positions,id',
                'brigade_id' => 'nullable|exists:brigades,id',
                'department_id' => 'nullable|exists:departments,id',
                'email' => 'nullable|email|max:100|unique:employees,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'status' => 'in:active,inactive'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Обновляем табельный номер
            if ($request->has('personnel_number')) {
                $employee->personnel_number = $request->personnel_number;
            }
            
            // Обновляем ФИО поля
            if ($request->has('last_name')) {
                $employee->last_name = $request->last_name;
            }
            if ($request->has('first_name')) {
                $employee->first_name = $request->first_name;
            }
            if ($request->has('middle_name')) {
                $employee->middle_name = $request->middle_name;
            }
            
            // Пересобираем full_name если изменились ФИО поля
            if ($request->hasAny(['last_name', 'first_name', 'middle_name'])) {
                $fullName = trim(implode(' ', array_filter([
                    $employee->last_name,
                    $employee->first_name,
                    $employee->middle_name
                ])));
                if (!empty($fullName)) {
                    $employee->full_name = $fullName;
                }
            }
            
            // Обновляем остальные поля
            if ($request->has('position_id')) {
                $employee->position_id = $request->position_id;
            }
            if ($request->has('brigade_id')) {
                $employee->brigade_id = $request->brigade_id;
            }
            if ($request->has('department_id')) {
                $employee->department_id = $request->department_id;
            }
            if ($request->has('email')) {
                $employee->email = $request->email;
            }
            if ($request->has('phone')) {
                $employee->phone = $request->phone;
            }
            if ($request->has('status')) {
                $employee->status = $request->status;
            }
            
            $employee->save();
            $employee->load(['position', 'brigade', 'department']);
            
            // Формируем актуальное ФИО
            $responseFullName = trim(implode(' ', array_filter([
                $employee->last_name,
                $employee->first_name,
                $employee->middle_name
            ])));
            
            if (empty($responseFullName)) {
                $responseFullName = $employee->full_name;
            }
            
            return response()->json([
                'id' => $employee->id,
                'personnel_number' => $employee->personnel_number,  // Добавлено
                'full_name' => $responseFullName,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'position' => $employee->position?->name ?? 'Не указана',
                'position_id' => $employee->position_id,
                'department' => $employee->department?->name ?? 'Не указано',
                'department_id' => $employee->department_id,
                'brigade' => $employee->brigade?->name ?? 'Не указана',
                'brigade_id' => $employee->brigade_id,
                'status' => $employee->status,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'updatedAt' => $employee->updated_at->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Employee not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update employee',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * 4.9 DELETE /employees/{id} - Удаление сотрудника
     */
    public function destroy($id)
    {
        try {
            $employee = Employee::findOrFail($id);
            
            // Проверяем наличие связанных записей
            if ($employee->userAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя удалить сотрудника с активной учетной записью'
                ], 400);
            }
            
            $employee->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Сотрудник удален'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Сотрудник не найден'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении сотрудника',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /employees/compliance/selected - Данные о соответствии для указанных сотрудников
     * 
     * @param Request $request - содержит массив employee_ids
     */
    public function getComplianceForSelected(Request $request)
    {
         try {
            $validator = Validator::make($request->all(), [
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:employees,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $employeeIds = $request->employee_ids;
            $total = count($employeeIds);
            
            // Соответствующие сотрудники (без просроченных обучений)
            $compliant = Employee::whereIn('id', $employeeIds)
                ->whereDoesntHave('employeeCourses', function($query) {
                    $query->where('status', 'expired');
                })
                ->count();
            
            $nonCompliant = $total - $compliant;
            $percentage = $total > 0 ? round(($compliant / $total) * 100) : 0;
            
            // Детальная информация по каждому сотруднику
            $employees = Employee::with(['position', 'department', 'employeeCourses' => function($query) {
                    $query->where('status', 'expired');
                }])
                ->whereIn('id', $employeeIds)
                ->get()
                ->map(function($employee) {
                    $fullName = trim(implode(' ', array_filter([
                        $employee->last_name,
                        $employee->first_name,
                        $employee->middle_name
                    ])));
                    
                    $hasExpired = $employee->employeeCourses->where('status', 'expired')->count() > 0;
                    $expiredCourses = $employee->employeeCourses->where('status', 'expired')->map(function($course) {
                        return [
                            'course_id' => $course->course_id,
                            'course_name' => $course->course?->name,
                            'expiration_date' => $course->expiration_date?->format('Y-m-d'),
                            'days_overdue' => abs(now()->diffInDays($course->expiration_date, false))
                        ];
                    });
                    
                    return [
                        'id' => $employee->id,
                        'full_name' => $fullName ?: $employee->full_name,
                        'personnel_number' => $employee->personnel_number,
                        'position' => $employee->position?->name ?? 'Не указана',
                        'department' => $employee->department?->name ?? 'Не указано',
                        'is_compliant' => !$hasExpired,
                        'expired_courses_count' => $expiredCourses->count(),
                        'expired_courses' => $expiredCourses
                    ];
                });
            
            // Статистика по категориям курсов для указанных сотрудников
            $categories = DB::table('course_categories')
                ->leftJoin('courses', 'course_categories.id', '=', 'courses.category_id')
                ->leftJoin('employee_courses', 'courses.id', '=', 'employee_courses.course_id')
                ->whereIn('employee_courses.employee_id', $employeeIds)
                ->select(
                    'course_categories.name',
                    DB::raw('COUNT(DISTINCT employee_courses.employee_id) as total_employees'),
                    DB::raw('SUM(CASE WHEN employee_courses.status = "expired" THEN 1 ELSE 0 END) as non_compliant_count')
                )
                ->whereNotNull('employee_courses.employee_id')
                ->groupBy('course_categories.id', 'course_categories.name')
                ->get();
            
            $categoriesData = $categories->map(function($category) {
                $totalEmployees = (int)$category->total_employees;
                $nonCompliant = (int)$category->non_compliant_count;
                $compliantCount = $totalEmployees - $nonCompliant;
                $percentage = $totalEmployees > 0 
                    ? round(($compliantCount / $totalEmployees) * 100) 
                    : 0;
                
                return [
                    'name' => $category->name,
                    'count' => $totalEmployees,
                    'percentage' => $percentage
                ];
            });
            
            return response()->json([
                'total' => $total,
                'compliant' => $compliant,
                'nonCompliant' => $nonCompliant,
                'compliancePercentage' => $percentage,
                'employees' => $employees,
                'categories' => $categoriesData
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Compliance for selected error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch compliance data for selected employees',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /employees/{id}/courses - Получить курсы сотрудника с разбивкой по срокам
     */
    public function getEmployeeCourses($id, Request $request)
    {
        try {
            $employee = Employee::findOrFail($id);
            
            // Формируем ФИО
            $fullName = trim(implode(' ', array_filter([
                $employee->last_name,
                $employee->first_name,
                $employee->middle_name
            ])));
            
            if (empty($fullName)) {
                $fullName = $employee->full_name;
            }
            
            // Получаем все обучения сотрудника с курсами
            $employeeCourses = EmployeeCourse::with(['course.category'])
                ->where('employee_id', $id)
                ->get();
            
            $today = now();
            $monthEnd = $today->copy()->addDays(30);
            $twoMonthsEnd = $today->copy()->addDays(60);
            
            // 1. Просроченные курсы
            $expiredCourses = $employeeCourses
                ->filter(function($ec) use ($today) {
                    return $ec->status === 'expired' || 
                        ($ec->expiration_date && $ec->expiration_date < $today);
                })
                ->map(function($ec) {
                    $daysOverdue = $ec->expiration_date 
                        ? abs(now()->diffInDays($ec->expiration_date, false))
                        : null;
                        
                    return [
                        'id' => $ec->id,
                        'course_id' => $ec->course_id,
                        'course_name' => $ec->course?->name ?? 'Неизвестный курс',
                        'category' => $ec->course?->category?->name,
                        'assigned_date' => $ec->assigned_date?->format('Y-m-d'),
                        'expiration_date' => $ec->expiration_date?->format('Y-m-d'),
                        'status' => 'expired',
                        'days_overdue' => $daysOverdue,
                        'certificate_number' => $ec->certificate_number
                    ];
                })
                ->values();
            
            // 2. Будут просрочены в течение месяца (до 30 дней)
            $expiringMonthCourses = $employeeCourses
                ->filter(function($ec) use ($today, $monthEnd) {
                    return $ec->status === 'active' && 
                        $ec->expiration_date && 
                        $ec->expiration_date >= $today && 
                        $ec->expiration_date <= $monthEnd;
                })
                ->map(function($ec) {
                    $daysLeft = $ec->expiration_date 
                        ? now()->diffInDays($ec->expiration_date, false)
                        : null;
                        
                    return [
                        'id' => $ec->id,
                        'course_id' => $ec->course_id,
                        'course_name' => $ec->course?->name ?? 'Неизвестный курс',
                        'category' => $ec->course?->category?->name,
                        'assigned_date' => $ec->assigned_date?->format('Y-m-d'),
                        'expiration_date' => $ec->expiration_date?->format('Y-m-d'),
                        'status' => 'expiring_soon',
                        'days_left' => max(0, $daysLeft),
                        'certificate_number' => $ec->certificate_number
                    ];
                })
                ->values();
            
            // 3. Будут просрочены в течение от 1 до 2 месяцев (31-60 дней)
            $expiringTwoMonthsCourses = $employeeCourses
                ->filter(function($ec) use ($monthEnd, $twoMonthsEnd) {
                    return $ec->status === 'active' && 
                        $ec->expiration_date && 
                        $ec->expiration_date > $monthEnd && 
                        $ec->expiration_date <= $twoMonthsEnd;
                })
                ->map(function($ec) {
                    $daysLeft = $ec->expiration_date 
                        ? now()->diffInDays($ec->expiration_date, false)
                        : null;
                        
                    return [
                        'id' => $ec->id,
                        'course_id' => $ec->course_id,
                        'course_name' => $ec->course?->name ?? 'Неизвестный курс',
                        'category' => $ec->course?->category?->name,
                        'assigned_date' => $ec->assigned_date?->format('Y-m-d'),
                        'expiration_date' => $ec->expiration_date?->format('Y-m-d'),
                        'status' => 'expiring_later',
                        'days_left' => max(0, $daysLeft),
                        'certificate_number' => $ec->certificate_number
                    ];
                })
                ->values();
            
            // 4. Активные курсы (со сроком более 2 месяцев)
            $activeCourses = $employeeCourses
                ->filter(function($ec) use ($twoMonthsEnd) {
                    return $ec->status === 'active' && 
                        ($ec->expiration_date === null || $ec->expiration_date > $twoMonthsEnd);
                })
                ->map(function($ec) {
                    return [
                        'id' => $ec->id,
                        'course_id' => $ec->course_id,
                        'course_name' => $ec->course?->name ?? 'Неизвестный курс',
                        'category' => $ec->course?->category?->name,
                        'assigned_date' => $ec->assigned_date?->format('Y-m-d'),
                        'expiration_date' => $ec->expiration_date?->format('Y-m-d'),
                        'status' => 'active',
                        'certificate_number' => $ec->certificate_number
                    ];
                })
                ->values();
            
            // 5. Требуемые курсы (без назначения)
            $allCourses = Course::all();
            $assignedCourseIds = $employeeCourses->pluck('course_id')->toArray();
            
            $requiredCourses = $allCourses
                ->filter(function($course) use ($assignedCourseIds) {
                    return !in_array($course->id, $assignedCourseIds);
                })
                ->map(function($course) {
                    return [
                        'course_id' => $course->id,
                        'course_name' => $course->name,
                        'category' => $course->category?->name,
                        'status' => 'required',
                        'assigned_date' => null,
                        'expiration_date' => null
                    ];
                })
                ->values();
            
            // Статистика
            $statistics = [
                'total_courses' => $employeeCourses->count() + $requiredCourses->count(),
                'assigned_count' => $employeeCourses->count(),
                'required_count' => $requiredCourses->count(),
                'expired_count' => $expiredCourses->count(),
                'expiring_month_count' => $expiringMonthCourses->count(),
                'expiring_two_months_count' => $expiringTwoMonthsCourses->count(),
                'active_count' => $activeCourses->count(),
                'compliance_rate' => $employeeCourses->count() > 0 
                    ? round(($activeCourses->count() / $employeeCourses->count()) * 100)
                    : 0
            ];
            
            return response()->json([
                'employee' => [
                    'id' => $employee->id,
                    'full_name' => $fullName,
                    'personnel_number' => $employee->personnel_number,
                    'position' => $employee->position?->name,
                    'department' => $employee->department?->name
                ],
                'courses' => [
                    'expired' => $expiredCourses,
                    'expiring_in_month' => $expiringMonthCourses,
                    'expiring_in_two_months' => $expiringTwoMonthsCourses,
                    'active' => $activeCourses,
                    'required' => $requiredCourses
                ],
                'statistics' => $statistics
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Employee not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Get employee courses error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch employee courses',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
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
     * 4.1 GET /employees - Список сотрудников
     */
    public function index(Request $request)    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 50);
            
            $query = Employee::with(['position', 'brigade'])
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
            
            // Поиск
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }
            
            $total = $query->count();
            $employees = $query->skip(($page - 1) * $limit)
                              ->take($limit)
                              ->get();
            
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
                    'full_name' => $fullName,
                    'last_name' => $employee->last_name,
                    'first_name' => $employee->first_name,
                    'middle_name' => $employee->middle_name,
                    'position' => $employee->position?->name ?? 'Не указана',
                    'position_id' => $employee->position_id,
                    'brigade' => $employee->brigade?->name ?? 'Не указана',
                    'brigade_id' => $employee->brigade_id,
                    'status' => $employee->status,
                    'email' => $employee->email,
                    'phone' => $employee->phone
                ];
            });
            
            return response()->json([
                'employees' => $formattedEmployees,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
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
            $employee = Employee::with(['position', 'brigade', 'userAccount'])
                ->findOrFail($id);
            
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
                'name' => $employee->full_name,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'position' => $employee->position?->name ?? 'Не указана',
                'position_id' => $employee->position_id,
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
                    return [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
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
            
            $employees = Employee::with(['position', 'brigade'])
                ->where('full_name', 'LIKE', "%{$query}%")
                ->orWhere('last_name', 'LIKE', "%{$query}%")
                ->orWhere('first_name', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->limit($limit)
                ->get()
                ->map(function($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                        'position' => $employee->position?->name ?? 'Не указана',
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
                'last_name' => 'required|string|max:50',
                'first_name' => 'required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'position_id' => 'nullable|exists:positions,id',
                'brigade_id' => 'nullable|exists:brigades,id',
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
                'full_name' => $fullName,
                'last_name' => $request->last_name,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'position_id' => $request->position_id,
                'brigade_id' => $request->brigade_id,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => $request->get('status', 'active')
            ]);
            
            $employee->load(['position', 'brigade']);
            
            // Формируем ответ с полным ФИО
            $responseFullName = trim(implode(' ', array_filter([
                $employee->last_name,
                $employee->first_name,
                $employee->middle_name
            ])));
            
            return response()->json([
                'id' => $employee->id,
                'full_name' => $responseFullName,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'position' => $employee->position?->name ?? 'Не указана',
                'position_id' => $employee->position_id,
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
                'full_name' => 'sometimes|string|max:100',
                'last_name' => 'nullable|string|max:50',
                'first_name' => 'nullable|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'position_id' => 'nullable|exists:positions,id',
                'brigade_id' => 'nullable|exists:brigades,id',
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
            
            $employee->update($request->all());
            $employee->load(['position', 'brigade']);
            
            return response()->json([
                'id' => $employee->id,
                'name' => $employee->full_name,
                'position' => $employee->position?->name ?? 'Не указана',
                'brigade' => $employee->brigade?->name ?? 'Не указана',
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
}
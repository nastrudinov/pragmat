<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\PositionCategory;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PositionController extends Controller
{
    /**
     * 7.1 GET /positions - Список должностей с подразделениями
     */
    public function index(Request $request)
    {
        try {
            $query = Position::with(['category', 'employees.department']);
            
            // Фильтр по категории
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            
            // Фильтр по подразделению (через сотрудников)
            if ($request->has('department_id')) {
                $query->whereHas('employees', function($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
            
            // Поиск по названию
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('name', 'LIKE', "%{$search}%");
            }
            
            // Сортировка
            $sortField = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);
            
            $positions = $query->get();
            
            $formattedPositions = $positions->map(function($position) {
                // Собираем уникальные подразделения, где есть сотрудники с этой должностью
                $departments = $position->employees
                    ->filter(function($employee) {
                        return $employee->department !== null;
                    })
                    ->unique('department_id')
                    ->map(function($employee) {
                        return [
                            'id' => $employee->department->id,
                            'name' => $employee->department->name,
                            'code' => $employee->department->code
                        ];
                    })
                    ->values();
                
                // Общее количество сотрудников на этой должности
                $employeesCount = $position->employees->count();
                
                // Количество сотрудников по подразделениям
                $employeesByDepartment = $position->employees
                    ->whereNotNull('department_id')
                    ->groupBy('department_id')
                    ->map(function($employees, $deptId) {
                        $dept = $employees->first()->department;
                        return [
                            'department_id' => $deptId,
                            'department_name' => $dept?->name ?? 'Без подразделения',
                            'count' => $employees->count()
                        ];
                    })
                    ->values();
                
                return [
                    'id' => $position->id,
                    'name' => $position->name,
                    'category' => $position->category?->name ?? 'Без категории',
                    'category_id' => $position->category_id,
                    'departments' => $departments,  // Уникальные подразделения
                    'employees_by_department' => $employeesByDepartment,  // Статистика по подразделениям
                    'employees_count' => $employeesCount,
                    'created_at' => $position->created_at?->toISOString(),
                    'updated_at' => $position->updated_at?->toISOString()
                ];
            });
            
            return response()->json([
                'positions' => $formattedPositions
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch positions',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 7.2 GET /positions/categories - Категории должностей
     */
    public function getCategories()
    {
        try {
            $categories = PositionCategory::orderBy('name')
                ->get()
                ->map(function($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'positions_count' => $category->positions()->count()
                    ];
                });
            
            // Для обратной совместимости с примером ответа (только имена)
            $categoryNames = $categories->pluck('name')->toArray();
            
            return response()->json([
                'categories' => $categoryNames,
                'categories_with_id' => $categories
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch categories',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить должность по ID с подразделениями
     */
    public function show($id)
    {
        try {
            $position = Position::with(['category', 'employees.department'])
                ->findOrFail($id);
            
            // Собираем уникальные подразделения
            $departments = $position->employees
                ->filter(function($employee) {
                    return $employee->department !== null;
                })
                ->unique('department_id')
                ->map(function($employee) {
                    return [
                        'id' => $employee->department->id,
                        'name' => $employee->department->name,
                        'code' => $employee->department->code,
                        'employees_count' => $position->employees
                            ->where('department_id', $employee->department->id)
                            ->count()
                    ];
                })
                ->values();
            
            // Сотрудники по подразделениям
            $employeesByDepartment = [];
            foreach ($departments as $dept) {
                $employeesByDepartment[] = [
                    'department' => $dept,
                    'employees' => $position->employees
                        ->where('department_id', $dept['id'])
                        ->map(function($employee) {
                            $fullName = trim(implode(' ', array_filter([
                                $employee->last_name,
                                $employee->first_name,
                                $employee->middle_name
                            ])));
                            
                            return [
                                'id' => $employee->id,
                                'full_name' => $fullName ?: $employee->full_name,
                                'status' => $employee->status,
                                'personnel_number' => $employee->personnel_number
                            ];
                        })
                        ->values()
                ];
            }
            
            // Сотрудники без подразделения
            $employeesWithoutDepartment = $position->employees
                ->whereNull('department_id')
                ->map(function($employee) {
                    $fullName = trim(implode(' ', array_filter([
                        $employee->last_name,
                        $employee->first_name,
                        $employee->middle_name
                    ])));
                    
                    return [
                        'id' => $employee->id,
                        'full_name' => $fullName ?: $employee->full_name,
                        'status' => $employee->status,
                        'personnel_number' => $employee->personnel_number
                    ];
                })
                ->values();
            
            return response()->json([
                'id' => $position->id,
                'name' => $position->name,
                'category' => $position->category?->name ?? 'Без категории',
                'category_id' => $position->category_id,
                'departments' => $departments,
                'employees_by_department' => $employeesByDepartment,
                'employees_without_department' => $employeesWithoutDepartment,
                'total_employees' => $position->employees->count(),
                'created_at' => $position->created_at?->toISOString(),
                'updated_at' => $position->updated_at?->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Position not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch position',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 7.3 POST /positions - Создание должности
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:positions,name',
                'category_id' => 'nullable|exists:position_categories,id',
                'category_name' => 'nullable|string|max:50'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Если указано название категории, пробуем найти или создать
            $categoryId = $request->category_id;
            if ($request->has('category_name') && !$categoryId) {
                $category = PositionCategory::firstOrCreate(
                    ['name' => $request->category_name],
                    ['name' => $request->category_name]
                );
                $categoryId = $category->id;
            }
            
            $position = Position::create([
                'name' => $request->name,
                'category_id' => $categoryId
            ]);
            
            $position->load('category');
            
            return response()->json([
                'id' => $position->id,
                'name' => $position->name,
                'category' => $position->category?->name ?? 'Без категории',
                'createdAt' => $position->created_at->toISOString()
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create position',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 7.4 PUT /positions/{id} - Обновление должности
     */
    public function update(Request $request, $id)
    {
        try {
            $position = Position::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:100|unique:positions,name,' . $id,
                'category_id' => 'nullable|exists:position_categories,id',
                'category_name' => 'nullable|string|max:50'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Обновляем название если передано
            if ($request->has('name')) {
                $position->name = $request->name;
            }
            
            // Обновляем категорию
            if ($request->has('category_id')) {
                $position->category_id = $request->category_id;
            } elseif ($request->has('category_name')) {
                $category = PositionCategory::firstOrCreate(
                    ['name' => $request->category_name],
                    ['name' => $request->category_name]
                );
                $position->category_id = $category->id;
            }
            
            $position->save();
            $position->load('category');
            
            return response()->json([
                'id' => $position->id,
                'name' => $position->name,
                'category' => $position->category?->name ?? 'Без категории',
                'updatedAt' => $position->updated_at->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Position not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update position',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 7.5 DELETE /positions/{id} - Удаление должности
     */
    public function destroy($id)
    {
        try {
            $position = Position::findOrFail($id);
            
            // Проверяем, есть ли сотрудники с этой должностью
            $employeesCount = $position->employees()->count();
            if ($employeesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Невозможно удалить должность. У {$employeesCount} сотрудников(а) указана эта должность."
                ], 400);
            }
            
            // Проверяем, есть ли требования по курсам
            $requirementsCount = $position->courseRequirements()->count();
            if ($requirementsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Невозможно удалить должность. Существует {$requirementsCount} требований к курсам для этой должности."
                ], 400);
            }
            
            $position->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Должность удалена'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Должность не найдена'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении должности',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить сотрудников по должности с группировкой по подразделениям
     */
    public function getEmployees($id)
    {
        try {
            $position = Position::with(['employees.department'])->findOrFail($id);
            
            // Группируем сотрудников по подразделениям
            $employeesByDepartment = $position->employees
                ->whereNotNull('department_id')
                ->groupBy('department_id')
                ->map(function($employees, $deptId) {
                    $dept = $employees->first()->department;
                    return [
                        'department_id' => $deptId,
                        'department_name' => $dept?->name ?? 'Без подразделения',
                        'department_code' => $dept?->code,
                        'employees' => $employees->map(function($employee) {
                            $fullName = trim(implode(' ', array_filter([
                                $employee->last_name,
                                $employee->first_name,
                                $employee->middle_name
                            ])));
                            
                            return [
                                'id' => $employee->id,
                                'full_name' => $fullName ?: $employee->full_name,
                                'personnel_number' => $employee->personnel_number,
                                'status' => $employee->status,
                                'email' => $employee->email,
                                'phone' => $employee->phone
                            ];
                        })->values()
                    ];
                })
                ->values();
            
            // Сотрудники без подразделения
            $employeesWithoutDepartment = $position->employees
                ->whereNull('department_id')
                ->map(function($employee) {
                    $fullName = trim(implode(' ', array_filter([
                        $employee->last_name,
                        $employee->first_name,
                        $employee->middle_name
                    ])));
                    
                    return [
                        'id' => $employee->id,
                        'full_name' => $fullName ?: $employee->full_name,
                        'personnel_number' => $employee->personnel_number,
                        'status' => $employee->status,
                        'email' => $employee->email,
                        'phone' => $employee->phone
                    ];
                })
                ->values();
            
            return response()->json([
                'position_id' => $position->id,
                'position_name' => $position->name,
                'total_employees' => $position->employees->count(),
                'employees_by_department' => $employeesByDepartment,
                'employees_without_department' => $employeesWithoutDepartment
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Position not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch employees',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить требования по курсам для должности
     */
    public function getCourseRequirements($id)
    {
        try {
            $position = Position::with(['courseRequirements.course'])->findOrFail($id);
            
            $requiredCourses = $position->courseRequirements
                ->where('is_required', true)
                ->map(function($requirement) {
                    return [
                        'course_id' => $requirement->course_id,
                        'course_name' => $requirement->course?->name ?? 'Неизвестный курс',
                        'duration_hours' => $requirement->course?->duration_hours,
                        'periodicity_months' => $requirement->course?->periodicity_months
                    ];
                });
            
            return response()->json([
                'position_id' => $position->id,
                'position_name' => $position->name,
                'required_courses' => $requiredCourses
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Position not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch course requirements',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить статистику по должности с разбивкой по подразделениям
     */
    public function getStatistics($id)
    {
        try {
            $position = Position::with(['employees.department', 'employees.employeeCourses'])
                ->findOrFail($id);
            
            $totalEmployees = $position->employees->count();
            
            // Статистика по подразделениям
            $departmentStats = [];
            
            foreach ($position->employees as $employee) {
                if ($employee->department_id) {
                    $deptId = $employee->department_id;
                    $deptName = $employee->department?->name ?? 'Без подразделения';
                    
                    if (!isset($departmentStats[$deptId])) {
                        $departmentStats[$deptId] = [
                            'department_id' => $deptId,
                            'department_name' => $deptName,
                            'total' => 0,
                            'compliant' => 0,
                            'non_compliant' => 0
                        ];
                    }
                    
                    $departmentStats[$deptId]['total']++;
                    
                    // Проверяем соответствие (нет просроченных курсов)
                    $hasExpired = $employee->employeeCourses->contains('status', 'expired');
                    if (!$hasExpired) {
                        $departmentStats[$deptId]['compliant']++;
                    } else {
                        $departmentStats[$deptId]['non_compliant']++;
                    }
                }
            }
            
            // Общая статистика
            $compliantEmployees = $position->employees->filter(function($employee) {
                return !$employee->employeeCourses->contains('status', 'expired');
            })->count();
            
            return response()->json([
                'position_id' => $position->id,
                'position_name' => $position->name,
                'total_employees' => $totalEmployees,
                'compliant_employees' => $compliantEmployees,
                'non_compliant_employees' => $totalEmployees - $compliantEmployees,
                'compliance_percentage' => $totalEmployees > 0 
                    ? round(($compliantEmployees / $totalEmployees) * 100)
                    : 0,
                'by_department' => array_values($departmentStats)
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Position not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
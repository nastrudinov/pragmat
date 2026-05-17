<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DepartmentController extends Controller
{
    /**
     * GET /departments - Список подразделений (дерево)
     */
    public function index(Request $request)
    {
        try {
            $query = Department::with(['parent', 'head', 'employees']);
            
            // Только активные
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Поиск
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('code', 'LIKE', "%{$search}%");
            }
            
            // Сортировка
            $sortField = $request->get('sort_by', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);
            
            if ($request->get('tree', false)) {
                // Возвращаем дерево
                $departments = $query->whereNull('parent_id')->get();
                $formattedDepartments = $departments->map(function($dept) {
                    return $this->formatDepartmentTree($dept);
                });
            } else {
                // Возвращаем плоский список
                $departments = $query->get();
                $formattedDepartments = $departments->map(function($dept) {
                    return $this->formatDepartment($dept);
                });
            }
            
            return response()->json([
                'departments' => $formattedDepartments
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch departments',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /departments/{id} - Детали подразделения
     */
    public function show($id)
    {
        try {
            $department = Department::with(['parent', 'head', 'children', 'employees.position'])
                ->findOrFail($id);
            
            // Статистика
            $employeesCount = $department->employees()->count();
            $childrenCount = $department->children()->count();
            
            $employees = $department->employees->map(function($employee) {
                $fullName = trim(implode(' ', array_filter([
                    $employee->last_name,
                    $employee->first_name,
                    $employee->middle_name
                ])));
                
                return [
                    'id' => $employee->id,
                    'full_name' => $fullName ?: $employee->full_name,
                    'position' => $employee->position?->name,
                    'status' => $employee->status
                ];
            });
            
            return response()->json([
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'parent_id' => $department->parent_id,
                'parent_name' => $department->parent?->name,
                'head' => $department->head ? [
                    'id' => $department->head->id,
                    'name' => $this->getFullName($department->head)
                ] : null,
                'phone' => $department->phone,
                'email' => $department->email,
                'description' => $department->description,
                'status' => $department->status,
                'statistics' => [
                    'employees_count' => $employeesCount,
                    'children_count' => $childrenCount
                ],
                'employees' => $employees,
                'children' => $department->children->map(fn($child) => $this->formatDepartment($child)),
                'created_at' => $department->created_at?->toISOString(),
                'updated_at' => $department->updated_at?->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch department',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /departments - Создание подразделения
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'code' => 'nullable|string|max:20|unique:departments,code',
                'parent_id' => 'nullable|exists:departments,id',
                'head_employee_id' => 'nullable|exists:employees,id',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer',
                'status' => 'in:active,inactive'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $department = Department::create($request->all());
            $department->load(['parent', 'head']);
            
            return response()->json([
                'success' => true,
                'department' => $this->formatDepartment($department),
                'message' => 'Подразделение создано'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create department',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * PUT /departments/{id} - Обновление подразделения
     */
    public function update(Request $request, $id)
    {
        try {
            $department = Department::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:100',
                'code' => 'nullable|string|max:20|unique:departments,code,' . $id,
                'parent_id' => 'nullable|exists:departments,id',
                'head_employee_id' => 'nullable|exists:employees,id',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer',
                'status' => 'in:active,inactive'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Нельзя назначить родителем самого себя
            if ($request->has('parent_id') && $request->parent_id == $id) {
                return response()->json([
                    'error' => 'Cannot set parent to itself'
                ], 400);
            }
            
            $department->update($request->all());
            $department->load(['parent', 'head']);
            
            return response()->json([
                'success' => true,
                'department' => $this->formatDepartment($department),
                'message' => 'Подразделение обновлено'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update department',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DELETE /departments/{id} - Удаление подразделения
     */
    public function destroy($id)
    {
        try {
            $department = Department::findOrFail($id);
            
            // Проверяем наличие сотрудников
            $employeesCount = $department->employees()->count();
            if ($employeesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Невозможно удалить подразделение. В нем {$employeesCount} сотрудников."
                ], 400);
            }
            
            // Проверяем наличие дочерних подразделений
            $childrenCount = $department->children()->count();
            if ($childrenCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Невозможно удалить подразделение. У него {$childrenCount} дочерних подразделений."
                ], 400);
            }
            
            $department->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Подразделение удалено'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Подразделение не найдено'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении подразделения',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /departments/{id}/employees - Сотрудники подразделения
     */
    public function getEmployees($id)
    {
        try {
            $department = Department::findOrFail($id);
            
            $employees = $department->employees()
                ->with(['position', 'brigade'])
                ->get()
                ->map(function($employee) {
                    $fullName = trim(implode(' ', array_filter([
                        $employee->last_name,
                        $employee->first_name,
                        $employee->middle_name
                    ])));
                    
                    return [
                        'id' => $employee->id,
                        'full_name' => $fullName ?: $employee->full_name,
                        'position' => $employee->position?->name,
                        'brigade' => $employee->brigade?->name,
                        'email' => $employee->email,
                        'phone' => $employee->phone,
                        'status' => $employee->status
                    ];
                });
            
            return response()->json([
                'department_id' => $department->id,
                'department_name' => $department->name,
                'total_employees' => $employees->count(),
                'employees' => $employees
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch employees',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Форматирование подразделения
     */
    private function formatDepartment($department)
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'parent_id' => $department->parent_id,
            'parent_name' => $department->parent?->name,
            'head' => $department->head ? [
                'id' => $department->head->id,
                'name' => $this->getFullName($department->head)
            ] : null,
            'employees_count' => $department->employees()->count(),
            'children_count' => $department->children()->count(),
            'status' => $department->status,
            'sort_order' => $department->sort_order,
            'created_at' => $department->created_at?->toISOString()
        ];
    }
    
    /**
     * Форматирование дерева подразделений
     */
    private function formatDepartmentTree($department)
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'head' => $department->head ? $this->getFullName($department->head) : null,
            'employees_count' => $department->employees()->count(),
            'status' => $department->status,
            'children' => $department->children->map(fn($child) => $this->formatDepartmentTree($child))
        ];
    }
    
    /**
     * Получить полное имя сотрудника
     */
    private function getFullName($employee)
    {
        if (!$employee) return null;
        
        $fullName = trim(implode(' ', array_filter([
            $employee->last_name,
            $employee->first_name,
            $employee->middle_name
        ])));
        
        return $fullName ?: $employee->full_name;
    }
}
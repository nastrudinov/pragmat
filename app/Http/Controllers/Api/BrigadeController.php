<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brigade;
use App\Models\Employee;
use App\Models\EmployeeCourse;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class BrigadeController extends Controller
{
    /**
     * 5.1 GET /brigades - Список бригад
     */
    public function index(Request $request)
    {
        try {
            $query = Brigade::with(['leader', 'employees']);
            
            // Поиск по названию
            if ($request->has('search')) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            }
            
            // Сортировка
            $sortField = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);
            
            $brigades = $query->get();
            
            $formattedBrigades = $brigades->map(function($brigade) {
                $membersCount = $brigade->employees->count();
                $activeMembers = $brigade->employees->where('status', 'active')->count();
                
                return [
                    'id' => $brigade->id,
                    'name' => $brigade->name,
                    'leader' => $brigade->leader?->full_name ?? 'Не назначен',
                    'leader_id' => $brigade->leader_employee_id,
                    'members' => $membersCount,
                    'active_members' => $activeMembers,
                    'status' => $membersCount > 0 ? 'active' : 'inactive',
                    'created_at' => $brigade->created_at?->format('Y-m-d')
                ];
            });
            
            return response()->json([
                'brigades' => $formattedBrigades
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch brigades',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 5.2 GET /brigades/{id} - Детали бригады
     */
    public function show($id)
    {
        try {
            $brigade = Brigade::with(['leader', 'employees.position', 'employees.employeeCourses'])
                ->findOrFail($id);
            
            $members = $brigade->employees->map(function($employee) {
                // Проверяем соответствие (нет просроченных обучений)
                $hasExpiredCourses = $employee->employeeCourses->contains('status', 'expired');
                
                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'position' => $employee->position?->name ?? 'Не указана',
                    'status' => $employee->status,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'is_compliant' => !$hasExpiredCourses
                ];
            });
            
            // Статистика по соответствию
            $totalMembers = $members->count();
            $compliantMembers = $members->where('is_compliant', true)->count();
            $nonCompliantMembers = $totalMembers - $compliantMembers;
            
            // Статистика по курсам бригады
            $courseStats = $this->getBrigadeCourseStats($brigade);
            
            return response()->json([
                'id' => $brigade->id,
                'name' => $brigade->name,
                'leader' => $brigade->leader?->full_name ?? 'Не назначен',
                'leader_id' => $brigade->leader_employee_id,
                'members' => $members,
                'statistics' => [
                    'total' => $totalMembers,
                    'compliant' => $compliantMembers,
                    'nonCompliant' => $nonCompliantMembers,
                    'compliancePercentage' => $totalMembers > 0 
                        ? round(($compliantMembers / $totalMembers) * 100) 
                        : 0
                ],
                'courseStats' => $courseStats,
                'created_at' => $brigade->created_at?->toISOString(),
                'updated_at' => $brigade->updated_at?->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Brigade not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch brigade details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 5.3 GET /brigades/{id}/members - Члены бригады
     */
    public function getMembers($id)
    {
        try {
            $brigade = Brigade::with(['employees.position'])->findOrFail($id);
            
            $members = $brigade->employees->map(function($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'position' => $employee->position?->name ?? 'Не указана',
                    'status' => $employee->status,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'joined_at' => $employee->created_at?->format('Y-m-d')
                ];
            });
            
            return response()->json([
                'brigadeId' => $brigade->id,
                'brigadeName' => $brigade->name,
                'totalMembers' => $members->count(),
                'members' => $members
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Brigade not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch brigade members',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 5.4 GET /brigades/statistics - Статистика бригад
     */
    public function getStatistics()
    {
        try {
            // Укомплектованность
            $totalEmployees = Employee::count();
            $employeesInBrigades = Employee::whereNotNull('brigade_id')->count();
            $requiredEmployees = $totalEmployees;
            
            $staffingPercentage = $requiredEmployees > 0 
                ? round(($employeesInBrigades / $requiredEmployees) * 100) 
                : 0;
            
            // Соответствие требованиям
            $compliantEmployees = Employee::whereDoesntHave('employeeCourses', function($query) {
                $query->where('status', 'expired');
            })->count();
            
            $requiredCompliant = $employeesInBrigades;
            $compliancePercentage = $requiredCompliant > 0 
                ? round(($compliantEmployees / $requiredCompliant) * 100) 
                : 0;
            
            // Внимание: критические и предупреждающие ситуации
            $criticalBrigades = [];
            $warningBrigades = [];
            
            $brigades = Brigade::with(['employees.employeeCourses'])->get();
            
            foreach ($brigades as $brigade) {
                $stats = $this->calculateBrigadeCompliance($brigade);
                
                if ($stats['compliancePercentage'] < 50) {
                    $criticalBrigades[] = [
                        'id' => $brigade->id,
                        'name' => $brigade->name,
                        'compliancePercentage' => $stats['compliancePercentage']
                    ];
                } elseif ($stats['compliancePercentage'] < 80) {
                    $warningBrigades[] = [
                        'id' => $brigade->id,
                        'name' => $brigade->name,
                        'compliancePercentage' => $stats['compliancePercentage']
                    ];
                }
            }
            
            // Сотрудники требующие внимания
            $employeesNeedingAttention = Employee::whereHas('employeeCourses', function($query) {
                $query->where('status', 'expired')
                    ->orWhere(function($q) {
                        $q->where('status', 'active')
                          ->whereNotNull('expiration_date')
                          ->where('expiration_date', '<=', now()->addDays(30));
                    });
            })->count();
            
            return response()->json([
                'staffing' => [
                    'current' => $employeesInBrigades,
                    'required' => $requiredEmployees,
                    'percentage' => $staffingPercentage
                ],
                'compliance' => [
                    'current' => $compliantEmployees,
                    'required' => $requiredCompliant,
                    'percentage' => $compliancePercentage
                ],
                'attention' => [
                    'count' => $employeesNeedingAttention,
                    'critical' => count($criticalBrigades),
                    'warning' => count($warningBrigades),
                    'criticalBrigades' => $criticalBrigades,
                    'warningBrigades' => $warningBrigades
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch brigade statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 5.5 POST /brigades - Создание бригады
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:brigades,name',
                'leader_employee_id' => 'nullable|exists:employees,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Проверяем, что руководитель не назначен уже в другую бригаду
            if ($request->has('leader_employee_id') && $request->leader_employee_id) {
                $existingLeader = Brigade::where('leader_employee_id', $request->leader_employee_id)
                    ->first();
                if ($existingLeader) {
                    return response()->json([
                        'error' => 'This employee is already a leader of another brigade'
                    ], 400);
                }
            }
            
            $brigade = Brigade::create([
                'name' => $request->name,
                'leader_employee_id' => $request->leader_employee_id
            ]);
            
            $brigade->load('leader');
            
            return response()->json([
                'id' => $brigade->id,
                'name' => $brigade->name,
                'leader' => $brigade->leader?->full_name ?? 'Не назначен',
                'members' => 0,
                'createdAt' => $brigade->created_at->toISOString()
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create brigade',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 5.6 PUT /brigades/{id} - Обновление бригады
     */
    public function update(Request $request, $id)
    {
        try {
            $brigade = Brigade::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:100|unique:brigades,name,' . $id,
                'leader_employee_id' => 'nullable|exists:employees,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Проверяем, что руководитель не назначен уже в другую бригаду
            if ($request->has('leader_employee_id') && $request->leader_employee_id) {
                $existingLeader = Brigade::where('leader_employee_id', $request->leader_employee_id)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingLeader) {
                    return response()->json([
                        'error' => 'This employee is already a leader of another brigade'
                    ], 400);
                }
            }
            
            if ($request->has('name')) {
                $brigade->name = $request->name;
            }
            
            if ($request->has('leader_employee_id')) {
                $brigade->leader_employee_id = $request->leader_employee_id;
            }
            
            $brigade->save();
            $brigade->load('leader');
            
            return response()->json([
                'id' => $brigade->id,
                'name' => $brigade->name,
                'leader' => $brigade->leader?->full_name ?? 'Не назначен',
                'updatedAt' => $brigade->updated_at->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Brigade not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update brigade',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 5.7 DELETE /brigades/{id} - Удаление бригады
     */
    public function destroy($id)
    {
        try {
            $brigade = Brigade::findOrFail($id);
            
            // Проверяем, есть ли сотрудники в бригаде
            $membersCount = $brigade->employees()->count();
            if ($membersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Невозможно удалить бригаду. В бригаде {$membersCount} сотрудников."
                ], 400);
            }
            
            $brigade->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Бригада удалена'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Бригада не найдена'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении бригады',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 5.8 POST /brigades/{id}/members - Добавить члена в бригаду
     */
    public function addMember(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $brigade = Brigade::findOrFail($id);
            $employee = Employee::findOrFail($request->employee_id);
            
            // Проверяем, не состоит ли уже в бригаде
            if ($employee->brigade_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сотрудник уже состоит в бригаде: ' . ($employee->brigade?->name ?? 'неизвестной')
                ], 400);
            }
            
            $employee->brigade_id = $brigade->id;
            $employee->save();
            
            return response()->json([
                'success' => true,
                'employeeId' => $employee->id,
                'employeeName' => $employee->full_name,
                'brigadeId' => $brigade->id,
                'brigadeName' => $brigade->name,
                'message' => 'Сотрудник добавлен в бригаду'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brigade or employee not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 5.9 DELETE /brigades/{id}/members/{employeeId} - Удалить члена из бригады
     */
    public function removeMember($id, $employeeId)
    {
        try {
            $brigade = Brigade::findOrFail($id);
            $employee = Employee::findOrFail($employeeId);
            
            // Проверяем, что сотрудник действительно в этой бригаде
            if ($employee->brigade_id != $brigade->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сотрудник не состоит в этой бригаде'
                ], 400);
            }
            
            // Если сотрудник - руководитель бригады, снимаем его с должности руководителя
            if ($brigade->leader_employee_id == $employeeId) {
                $brigade->leader_employee_id = null;
                $brigade->save();
            }
            
            $employee->brigade_id = null;
            $employee->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Сотрудник удален из бригады'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brigade or employee not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить статистику по курсам бригады
     */
    private function getBrigadeCourseStats($brigade)
    {
        $employeeIds = $brigade->employees->pluck('id');
        
        if ($employeeIds->isEmpty()) {
            return [
                'total' => 0,
                'completed' => 0,
                'expired' => 0,
                'completionRate' => 0
            ];
        }
        
        $totalCourses = EmployeeCourse::whereIn('employee_id', $employeeIds)->count();
        $completedCourses = EmployeeCourse::whereIn('employee_id', $employeeIds)
            ->where('status', 'active')
            ->whereNotNull('completed_date')
            ->count();
        $expiredCourses = EmployeeCourse::whereIn('employee_id', $employeeIds)
            ->where('status', 'expired')
            ->count();
        
        return [
            'total' => $totalCourses,
            'completed' => $completedCourses,
            'expired' => $expiredCourses,
            'completionRate' => $totalCourses > 0 ? round(($completedCourses / $totalCourses) * 100) : 0
        ];
    }
    
    /**
     * Рассчитать процент соответствия бригады
     */
    private function calculateBrigadeCompliance($brigade)
    {
        $totalMembers = $brigade->employees->count();
        if ($totalMembers === 0) {
            return ['compliancePercentage' => 0, 'compliant' => 0, 'total' => 0];
        }
        
        $compliantMembers = 0;
        foreach ($brigade->employees as $employee) {
            $hasExpired = $employee->employeeCourses->contains('status', 'expired');
            if (!$hasExpired) {
                $compliantMembers++;
            }
        }
        
        return [
            'total' => $totalMembers,
            'compliant' => $compliantMembers,
            'compliancePercentage' => round(($compliantMembers / $totalMembers) * 100)
        ];
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brigade;
use App\Models\Employee;
use App\Models\EmployeeCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;

class BrigadeController extends Controller
{
    /**
     * Время жизни кэша (минуты)
     */
    private $cacheTTL = 15;
    private $longCacheTTL = 60;
    
    /**
     * 5.1 GET /brigades - Список бригад
     */
    public function index(Request $request)
    {
        try {
            $cacheKey = $this->getCacheKey('list', $request);
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($request) {
                $query = Brigade::with(['leader:id,full_name', 'employees:id,brigade_id,status'])
                    ->select(['id', 'name', 'leader_employee_id', 'created_at']);
                
                if ($request->filled('search')) {
                    $query->where('name', 'LIKE', "%{$request->search}%");
                }
                
                $sortField = $request->get('sort_by', 'name');
                $sortDirection = $request->get('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
                
                $brigades = $query->get();
                
                return [
                    'brigades' => $brigades->map(fn($brigade) => $this->formatBrigadeList($brigade))
                ];
            });
            
            return response()->json($result, 200);
            
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
            $cacheKey = "brigade_details_{$id}";
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($id) {
                $brigade = Brigade::with([
                    'leader:id,full_name',
                    'employees:id,full_name,position_id,brigade_id,status,email,phone,created_at',
                    'employees.position:id,name',
                    'employees.employeeCourses:id,employee_id,status'
                ])->findOrFail($id);
                
                $members = $brigade->employees->map(function($employee) {
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
                
                $totalMembers = $members->count();
                $compliantMembers = $members->where('is_compliant', true)->count();
                
                // Оптимизированный расчет статистики курсов
                $courseStats = $this->getBrigadeCourseStatsOptimized($brigade);
                
                return [
                    'id' => $brigade->id,
                    'name' => $brigade->name,
                    'leader' => $brigade->leader?->full_name ?? 'Не назначен',
                    'leader_id' => $brigade->leader_employee_id,
                    'members' => $members,
                    'statistics' => [
                        'total' => $totalMembers,
                        'compliant' => $compliantMembers,
                        'nonCompliant' => $totalMembers - $compliantMembers,
                        'compliancePercentage' => $totalMembers > 0 ? round(($compliantMembers / $totalMembers) * 100) : 0
                    ],
                    'courseStats' => $courseStats,
                    'created_at' => $brigade->created_at?->toISOString(),
                    'updated_at' => $brigade->updated_at?->toISOString()
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Brigade not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch brigade details', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 5.3 GET /brigades/{id}/members - Члены бригады
     */
    public function getMembers($id)
    {
        try {
            $cacheKey = "brigade_members_{$id}";
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($id) {
                $brigade = Brigade::with(['employees:id,full_name,position_id,brigade_id,status,email,phone,created_at', 
                    'employees.position:id,name'])
                    ->select(['id', 'name'])
                    ->findOrFail($id);
                
                $members = $brigade->employees->map(fn($employee) => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'position' => $employee->position?->name ?? 'Не указана',
                    'status' => $employee->status,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'joined_at' => $employee->created_at?->format('Y-m-d')
                ]);
                
                return [
                    'brigadeId' => $brigade->id,
                    'brigadeName' => $brigade->name,
                    'totalMembers' => $members->count(),
                    'members' => $members
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Brigade not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch brigade members', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 5.4 GET /brigades/statistics - Статистика бригад
     */
    public function getStatistics()
    {
        try {
            $cacheKey = 'brigades_statistics';
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->longCacheTTL), function() {
                // Укомплектованность
                $totalEmployees = Employee::count();
                $employeesInBrigades = Employee::whereNotNull('brigade_id')->count();
                
                $staffingPercentage = $totalEmployees > 0 ? round(($employeesInBrigades / $totalEmployees) * 100) : 0;
                
                // Соответствие требованиям
                $compliantEmployees = Employee::whereDoesntHave('employeeCourses', fn($q) => $q->where('status', 'expired'))->count();
                
                $compliancePercentage = $employeesInBrigades > 0 ? round(($compliantEmployees / $employeesInBrigades) * 100) : 0;
                
                // Анализ бригад одним запросом
                $brigades = Brigade::with(['employees:id,brigade_id', 'employees.employeeCourses:id,employee_id,status'])
                    ->get(['id', 'name']);
                
                $criticalBrigades = [];
                $warningBrigades = [];
                
                foreach ($brigades as $brigade) {
                    $stats = $this->calculateBrigadeComplianceOptimized($brigade);
                    
                    if ($stats['compliancePercentage'] < 50) {
                        $criticalBrigades[] = ['id' => $brigade->id, 'name' => $brigade->name, 'compliancePercentage' => $stats['compliancePercentage']];
                    } elseif ($stats['compliancePercentage'] < 80) {
                        $warningBrigades[] = ['id' => $brigade->id, 'name' => $brigade->name, 'compliancePercentage' => $stats['compliancePercentage']];
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
                
                return [
                    'staffing' => [
                        'current' => $employeesInBrigades,
                        'required' => $totalEmployees,
                        'percentage' => $staffingPercentage
                    ],
                    'compliance' => [
                        'current' => $compliantEmployees,
                        'required' => $employeesInBrigades,
                        'percentage' => $compliancePercentage
                    ],
                    'attention' => [
                        'count' => $employeesNeedingAttention,
                        'critical' => count($criticalBrigades),
                        'warning' => count($warningBrigades),
                        'criticalBrigades' => $criticalBrigades,
                        'warningBrigades' => $warningBrigades
                    ]
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch brigade statistics', 'message' => $e->getMessage()], 500);
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
                return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            
            if ($request->filled('leader_employee_id')) {
                $existingLeader = Brigade::where('leader_employee_id', $request->leader_employee_id)->first();
                if ($existingLeader) {
                    return response()->json(['error' => 'This employee is already a leader of another brigade'], 400);
                }
            }
            
            $brigade = Brigade::create([
                'name' => $request->name,
                'leader_employee_id' => $request->leader_employee_id
            ]);
            
            $brigade->load('leader:id,full_name');
            $this->clearBrigadeCache();
            
            return response()->json([
                'id' => $brigade->id,
                'name' => $brigade->name,
                'leader' => $brigade->leader?->full_name ?? 'Не назначен',
                'members' => 0,
                'createdAt' => $brigade->created_at->toISOString()
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create brigade', 'message' => $e->getMessage()], 500);
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
                return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            
            if ($request->filled('leader_employee_id')) {
                $existingLeader = Brigade::where('leader_employee_id', $request->leader_employee_id)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingLeader) {
                    return response()->json(['error' => 'This employee is already a leader of another brigade'], 400);
                }
            }
            
            if ($request->has('name')) $brigade->name = $request->name;
            if ($request->has('leader_employee_id')) $brigade->leader_employee_id = $request->leader_employee_id;
            $brigade->save();
            
            $this->clearBrigadeCache($id);
            
            return response()->json([
                'id' => $brigade->id,
                'name' => $brigade->name,
                'leader' => $brigade->leader?->full_name ?? 'Не назначен',
                'updatedAt' => $brigade->updated_at->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Brigade not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update brigade', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 5.7 DELETE /brigades/{id} - Удаление бригады
     */
    public function destroy($id)
    {
        try {
            $brigade = Brigade::findOrFail($id);
            
            $membersCount = $brigade->employees()->count();
            if ($membersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Невозможно удалить бригаду. В бригаде {$membersCount} сотрудников."
                ], 400);
            }
            
            $brigade->delete();
            $this->clearBrigadeCache($id);
            
            return response()->json(['success' => true, 'message' => 'Бригада удалена'], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Бригада не найдена'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Ошибка при удалении бригады', 'error' => $e->getMessage()], 500);
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
                return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            
            $brigade = Brigade::findOrFail($id);
            $employee = Employee::findOrFail($request->employee_id);
            
            if ($employee->brigade_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сотрудник уже состоит в бригаде: ' . ($employee->brigade?->name ?? 'неизвестной')
                ], 400);
            }
            
            $employee->brigade_id = $brigade->id;
            $employee->save();
            
            $this->clearBrigadeCache($id);
            
            return response()->json([
                'success' => true,
                'employeeId' => $employee->id,
                'employeeName' => $employee->full_name,
                'brigadeId' => $brigade->id,
                'brigadeName' => $brigade->name,
                'message' => 'Сотрудник добавлен в бригаду'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Brigade or employee not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to add member', 'error' => $e->getMessage()], 500);
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
            
            if ($employee->brigade_id != $brigade->id) {
                return response()->json(['success' => false, 'message' => 'Сотрудник не состоит в этой бригаде'], 400);
            }
            
            if ($brigade->leader_employee_id == $employeeId) {
                $brigade->leader_employee_id = null;
                $brigade->save();
            }
            
            $employee->brigade_id = null;
            $employee->save();
            
            $this->clearBrigadeCache($id);
            
            return response()->json(['success' => true, 'message' => 'Сотрудник удален из бригады'], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Brigade or employee not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to remove member', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Получить статистику по курсам бригады (оптимизированная)
     */
    private function getBrigadeCourseStatsOptimized($brigade)
    {
        $employeeIds = $brigade->employees->pluck('id');
        
        if ($employeeIds->isEmpty()) {
            return ['total' => 0, 'completed' => 0, 'expired' => 0, 'completionRate' => 0];
        }
        
        $stats = EmployeeCourse::whereIn('employee_id', $employeeIds)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "active" AND completed_date IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "expired" THEN 1 ELSE 0 END) as expired
            ')
            ->first();
        
        $total = $stats->total ?? 0;
        $completed = $stats->completed ?? 0;
        
        return [
            'total' => $total,
            'completed' => $completed,
            'expired' => $stats->expired ?? 0,
            'completionRate' => $total > 0 ? round(($completed / $total) * 100) : 0
        ];
    }
    
    /**
     * Рассчитать процент соответствия бригады (оптимизированная)
     */
    private function calculateBrigadeComplianceOptimized($brigade)
    {
        $totalMembers = $brigade->employees->count();
        if ($totalMembers === 0) {
            return ['compliancePercentage' => 0, 'compliant' => 0, 'total' => 0];
        }
        
        $employeeIds = $brigade->employees->pluck('id');
        
        if ($employeeIds->isEmpty()) {
            return ['total' => 0, 'compliant' => 0, 'compliancePercentage' => 0];
        }
        
        $employeesWithExpired = EmployeeCourse::whereIn('employee_id', $employeeIds)
            ->where('status', 'expired')
            ->distinct('employee_id')
            ->count('employee_id');
        
        $compliantMembers = $totalMembers - $employeesWithExpired;
        
        return [
            'total' => $totalMembers,
            'compliant' => $compliantMembers,
            'compliancePercentage' => round(($compliantMembers / $totalMembers) * 100)
        ];
    }
    
    /**
     * Форматирование бригады для списка
     */
    private function formatBrigadeList($brigade)
    {
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
    }
    
    /**
     * Получить ключ кэша
     */
    private function getCacheKey($prefix, $request)
    {
        $params = array_merge(
            $request->only(['search', 'sort_by', 'sort_direction']),
            ['page' => $request->get('page', 1)]
        );
        return "brigades_{$prefix}_" . md5(json_encode($params));
    }
    
    /**
     * Очистка кэша бригад
     */
    private function clearBrigadeCache($id = null)
    {
        Cache::flush(); // Простой способ очистки всего кэша
        
        // Более точная очистка (опционально):
        // if ($id) {
        //     Cache::forget("brigade_details_{$id}");
        //     Cache::forget("brigade_members_{$id}");
        // }
        // Cache::forget('brigades_statistics');
        // Cache::forget('brigades_list_*');
    }
    
    /**
     * Очистка кэша (эндпоинт для админов)
     */
    public function clearCache()
    {
        Cache::flush();
        return response()->json(['success' => true, 'message' => 'Brigade cache cleared']);
    }
}
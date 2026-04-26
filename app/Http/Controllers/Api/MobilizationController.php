<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mobilization;
use App\Models\MobilizationStage;
use App\Models\MobilizationEmployee;
use App\Models\StageHistory;
use App\Models\Employee;
use App\Models\Brigade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MobilizationController extends Controller
{
    /**
     * 9.1 GET /processes - Список процессов (включая мобилизации)
     */
    public function index(Request $request)
    {
        try {
            $query = Mobilization::with(['currentStage', 'creator', 'mobilizationEmployees.employee']);
            
            // Фильтр по статусу
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Фильтр по датам
            if ($request->has('from_date')) {
                $query->where('start_date', '>=', $request->from_date);
            }
            
            if ($request->has('to_date')) {
                $query->where('end_date', '<=', $request->to_date);
            }
            
            // Поиск по названию
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('object_name', 'LIKE', "%{$search}%");
            }
            
            // Сортировка
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);
            
            $processes = $query->get();
            
            $formattedProcesses = $processes->map(function($process) {
                return [
                    'id' => $process->id,
                    'title' => $process->title,
                    'type' => 'mobilization',
                    'status' => $process->status,
                    'currentStage' => $process->currentStage ? [
                        'id' => $process->currentStage->id,
                        'name' => $process->currentStage->name
                    ] : null,
                    'startDate' => $process->start_date?->format('Y-m-d'),
                    'endDate' => $process->end_date?->format('Y-m-d'),
                    'employeesCount' => $process->mobilizationEmployees->count(),
                    'createdBy' => $process->creator?->full_name,
                    'createdAt' => $process->created_at?->toISOString()
                ];
            });
            
            return response()->json([
                'processes' => $formattedProcesses
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch processes',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 9.2 GET /processes/mobilization - Список мобилизаций
     */
    public function getMobilizations(Request $request)
    {
        try {
            $query = Mobilization::with(['currentStage', 'mobilizationEmployees.employee.brigade']);
            
            // Фильтр по статусу
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Фильтр по бригаде
            if ($request->has('brigade_id')) {
                $query->whereHas('mobilizationEmployees', function($q) use ($request) {
                    $q->whereHas('employee', function($sub) use ($request) {
                        $sub->where('brigade_id', $request->brigade_id);
                    });
                });
            }
            
            // Поиск
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('object_name', 'LIKE', "%{$search}%");
            }
            
            // Пагинация
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $total = $query->count();
            
            $mobilizations = $query->skip(($page - 1) * $limit)
                                ->take($limit)
                                ->get();
            
            $formattedMobilizations = $mobilizations->map(function($mobilization) {
                // Получаем уникальные бригады из сотрудников мобилизации
                $brigades = $mobilization->mobilizationEmployees
                    ->map(function($me) {
                        return $me->employee->brigade;
                    })
                    ->filter()
                    ->unique('id')
                    ->pluck('name')
                    ->implode(', ');
                
                $brigadeName = $brigades ?: 'Не указана';
                
                return [
                    'id' => $mobilization->id,
                    'title' => $mobilization->title,
                    'objectName' => $mobilization->object_name,
                    'status' => $mobilization->status,
                    'currentStage' => $mobilization->currentStage?->name ?? 'Не указан',
                    'startDate' => $mobilization->start_date?->format('Y-m-d'),
                    'endDate' => $mobilization->end_date?->format('Y-m-d'),
                    'brigade' => $brigadeName,
                    'employeesCount' => $mobilization->mobilizationEmployees->count()
                ];
            });
            
            return response()->json([
                'mobilizations' => $formattedMobilizations,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch mobilizations',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * 9.3 GET /processes/mobilization/{id} - Детали мобилизации
     */
    public function showMobilization($id)
    {
        try {
            $mobilization = Mobilization::with([
                'currentStage',
                'creator',
                'mobilizationEmployees.employee.position',
                'mobilizationEmployees.employee.employeeCourses',
                'stageHistories.stage'
            ])->findOrFail($id);
            
            // Расчет процента соответствия по курсам
            $totalEmployees = $mobilization->mobilizationEmployees->count();
            $compliantEmployees = 0;
            
            foreach ($mobilization->mobilizationEmployees as $me) {
                $employee = $me->employee;
                $hasExpiredCourses = $employee->employeeCourses->contains('status', 'expired');
                if (!$hasExpiredCourses) {
                    $compliantEmployees++;
                }
            }
            
            $compliancePercentage = $totalEmployees > 0 
                ? round(($compliantEmployees / $totalEmployees) * 100) 
                : 0;
            
            // Форматирование этапов
            $stages = $mobilization->stageHistories->map(function($history) {
                return [
                    'id' => $history->stage_id,
                    'name' => $history->stage?->name,
                    'status' => $history->status,
                    'startedAt' => $history->started_at?->format('Y-m-d H:i:s'),
                    'completedAt' => $history->completed_at?->format('Y-m-d H:i:s'),
                    'notes' => $history->notes
                ];
            });
            
            // Форматирование сотрудников
            $employees = $mobilization->mobilizationEmployees->map(function($me) {
                $employee = $me->employee;
                $hasExpiredCourses = $employee->employeeCourses->contains('status', 'expired');
                $expiredCourses = $employee->employeeCourses
                    ->where('status', 'expired')
                    ->pluck('course_id')
                    ->toArray();
                
                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'position' => $employee->position?->name ?? 'Не указана',
                    'role' => $me->role ?? 'Участник',
                    'status' => $employee->status,
                    'isCompliant' => !$hasExpiredCourses,
                    'expiredCoursesCount' => count($expiredCourses),
                    'assignedAt' => $me->assigned_at?->format('Y-m-d H:i:s')
                ];
            });
            
            // Форматирование SLA для текущего этапа
            $slaText = null;
            if ($mobilization->currentStage && $mobilization->currentStage->sla_hours) {
                $slaText = $mobilization->currentStage->sla_hours . ' часов';
            }
            
            return response()->json([
                'id' => $mobilization->id,
                'title' => $mobilization->title,
                'objectName' => $mobilization->object_name,
                'startDate' => $mobilization->start_date?->format('Y-m-d'),
                'endDate' => $mobilization->end_date?->format('Y-m-d'),
                'status' => $mobilization->status,
                'currentStage' => $mobilization->currentStage ? [
                    'id' => $mobilization->currentStage->id,
                    'name' => $mobilization->currentStage->name,
                    'sla' => $slaText,
                    'description' => $mobilization->currentStage->description
                ] : null,
                'stages' => $stages,
                'employees' => $employees,
                'compliancePercentage' => $compliancePercentage,
                'totalEmployees' => $totalEmployees,
                'compliantEmployees' => $compliantEmployees,
                'createdBy' => $mobilization->creator?->full_name,
                'createdAt' => $mobilization->created_at?->toISOString(),
                'updatedAt' => $mobilization->updated_at?->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Mobilization not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch mobilization details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 9.4 POST /processes/mobilization - Создание мобилизации
     */
    public function storeMobilization(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:200',
                'object_name' => 'nullable|string|max:200',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'in:active,blocked,completed',
                'employee_ids' => 'nullable|array',
                'employee_ids.*' => 'exists:employees,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Получаем первый этап мобилизации
            $firstStage = MobilizationStage::orderBy('sort_order')->first();
            if (!$firstStage) {
                return response()->json([
                    'error' => 'No mobilization stages configured'
                ], 400);
            }
            
            // Создаем мобилизацию
            $mobilization = Mobilization::create([
                'title' => $request->title,
                'object_name' => $request->object_name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->get('status', 'active'),
                'current_stage_id' => $firstStage->id,
                'created_by' => auth()->user()?->employee_id ?? null
            ]);
            
            // Добавляем сотрудников
            if ($request->has('employee_ids')) {
                foreach ($request->employee_ids as $employeeId) {
                    MobilizationEmployee::create([
                        'mobilization_id' => $mobilization->id,
                        'employee_id' => $employeeId,
                        'role' => 'Участник',
                        'assigned_at' => now()
                    ]);
                }
            }
            
            // Создаем историю этапов
            StageHistory::create([
                'mobilization_id' => $mobilization->id,
                'stage_id' => $firstStage->id,
                'started_at' => now(),
                'status' => 'in_progress'
            ]);
            
            DB::commit();
            
            return response()->json([
                'id' => $mobilization->id,
                'title' => $mobilization->title,
                'objectName' => $mobilization->object_name,
                'startDate' => $mobilization->start_date?->format('Y-m-d'),
                'endDate' => $mobilization->end_date?->format('Y-m-d'),
                'status' => $mobilization->status,
                'createdAt' => $mobilization->created_at->toISOString()
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create mobilization',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 9.5 PUT /processes/mobilization/{id}/stage - Сменить этап
     */
    public function changeStage(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'stage_id' => 'required|exists:mobilization_stages,id',
                'notes' => 'nullable|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $mobilization = Mobilization::findOrFail($id);
            $newStage = MobilizationStage::findOrFail($request->stage_id);
            
            DB::beginTransaction();
            
            // Завершаем текущий этап
            $currentHistory = StageHistory::where('mobilization_id', $id)
                ->where('stage_id', $mobilization->current_stage_id)
                ->whereNull('completed_at')
                ->first();
            
            if ($currentHistory) {
                $currentHistory->completed_at = now();
                $currentHistory->status = 'completed';
                $currentHistory->save();
            }
            
            // Начинаем новый этап
            StageHistory::create([
                'mobilization_id' => $id,
                'stage_id' => $newStage->id,
                'started_at' => now(),
                'status' => 'in_progress',
                'notes' => $request->notes
            ]);
            
            // Обновляем текущий этап
            $mobilization->current_stage_id = $newStage->id;
            $mobilization->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'newStage' => [
                    'id' => $newStage->id,
                    'name' => $newStage->name,
                    'sla' => $newStage->sla_hours ? $newStage->sla_hours . ' часов' : null
                ],
                'updatedAt' => now()->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mobilization or stage not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to change stage',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Добавить сотрудников в мобилизацию
     */
    public function addEmployees(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:employees,id',
                'role' => 'nullable|string|max:50'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $mobilization = Mobilization::findOrFail($id);
            
            $added = 0;
            $skipped = 0;
            
            foreach ($request->employee_ids as $employeeId) {
                // Проверяем, не добавлен ли уже
                $exists = MobilizationEmployee::where('mobilization_id', $id)
                    ->where('employee_id', $employeeId)
                    ->exists();
                
                if (!$exists) {
                    MobilizationEmployee::create([
                        'mobilization_id' => $id,
                        'employee_id' => $employeeId,
                        'role' => $request->get('role', 'Участник'),
                        'assigned_at' => now()
                    ]);
                    $added++;
                } else {
                    $skipped++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Добавлено {$added} сотрудников. Пропущено (уже в мобилизации): {$skipped}",
                'added' => $added,
                'skipped' => $skipped
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mobilization not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Удалить сотрудника из мобилизации
     */
    public function removeEmployee($id, $employeeId)
    {
        try {
            $mobilizationEmployee = MobilizationEmployee::where('mobilization_id', $id)
                ->where('employee_id', $employeeId)
                ->firstOrFail();
            
            $mobilizationEmployee->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Сотрудник удален из мобилизации'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found in this mobilization'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Обновить статус мобилизации
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:active,blocked,completed'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $mobilization = Mobilization::findOrFail($id);
            $mobilization->status = $request->status;
            
            // Если завершаем, завершаем текущий этап
            if ($request->status === 'completed') {
                $currentHistory = StageHistory::where('mobilization_id', $id)
                    ->where('stage_id', $mobilization->current_stage_id)
                    ->whereNull('completed_at')
                    ->first();
                
                if ($currentHistory) {
                    $currentHistory->completed_at = now();
                    $currentHistory->status = 'completed';
                    $currentHistory->save();
                }
            }
            
            $mobilization->save();
            
            return response()->json([
                'success' => true,
                'status' => $mobilization->status,
                'updatedAt' => $mobilization->updated_at->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mobilization not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCourse;
use App\Models\Employee;
use App\Models\Course;
use App\Models\Brigade;
use App\Models\Position;
use App\Models\PositionCourseRequirement;
use App\Models\BrigadeCourseRequirement;
use App\Models\TrainingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TrainingController extends Controller
{
    /**
     * Время жизни кэша (минуты)
     */
    private $cacheTTL = 15;
    private $longCacheTTL = 60;
    
    /**
     * 2.1 GET /trainings/expired - Просроченные обучения
     */
    public function getExpiredTrainings(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            
            $cacheKey = $this->getCacheKey('expired', $request);
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($request, $page, $limit) {
                $query = EmployeeCourse::with(['employee:id,full_name,brigade_id,position_id', 'employee.position:id,name', 'employee.brigade:id,name', 'course:id,name'])
                    ->select(['id', 'employee_id', 'course_id', 'status', 'expiration_date'])
                    ->where('status', 'expired')
                    ->whereNotNull('expiration_date')
                    ->where('expiration_date', '<', now())
                    ->orderBy('expiration_date', 'asc');
                
                if ($request->filled('brigade_id')) {
                    $query->whereHas('employee', fn($q) => $q->where('brigade_id', $request->brigade_id));
                }
                
                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->whereHas('employee', fn($q) => $q->where('full_name', 'LIKE', "%{$search}%"));
                }
                
                $total = $query->count();
                $hasMore = ($page * $limit) < $total;
                
                $trainings = $query->skip(($page - 1) * $limit)->take($limit)->get();
                
                return [
                    'trainings' => $trainings->map(fn($training) => $this->formatExpiredTraining($training)),
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'hasMore' => $hasMore
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch expired trainings', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.2 GET /trainings/expiring/{days} - Истекающие обучения
     */
    public function getExpiringTrainings($days, Request $request)
    {
        try {
            $days = (int) $days;
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $today = now();
            $expiryDate = now()->addDays($days);
            
            $cacheKey = $this->getCacheKey("expiring_{$days}", $request);
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($request, $today, $expiryDate, $page, $limit) {
                $query = EmployeeCourse::with(['employee:id,full_name,brigade_id,position_id', 'employee.position:id,name', 'employee.brigade:id,name', 'course:id,name'])
                    ->select(['id', 'employee_id', 'course_id', 'status', 'expiration_date'])
                    ->where('status', 'active')
                    ->whereNotNull('expiration_date')
                    ->whereBetween('expiration_date', [$today, $expiryDate])
                    ->orderBy('expiration_date', 'asc');
                
                if ($request->filled('brigade_id')) {
                    $query->whereHas('employee', fn($q) => $q->where('brigade_id', $request->brigade_id));
                }
                if ($request->filled('employee_id')) {
                    $query->where('employee_id', $request->employee_id);
                }
                
                $total = $query->count();
                $hasMore = ($page * $limit) < $total;
                $trainings = $query->skip(($page - 1) * $limit)->take($limit)->get();
                
                return [
                    'trainings' => $trainings->map(fn($training) => $this->formatExpiringTraining($training, $today)),
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'hasMore' => $hasMore
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch expiring trainings', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.3 GET /trainings/{id} - Детали обучения
     */
    public function show($id)
    {
        try {
            $cacheKey = "training_details_{$id}";
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($id) {
                $training = EmployeeCourse::with(['employee.position', 'employee.brigade', 'course'])
                    ->findOrFail($id);
                
                $history = $this->getTrainingHistory($training);
                
                return [
                    'id' => $training->id,
                    'trainingName' => $training->course?->name ?? 'Неизвестный курс',
                    'trainingId' => $training->course_id,
                    'employee' => [
                        'id' => $training->employee->id,
                        'name' => $training->employee->full_name,
                        'position' => $training->employee->position?->name ?? 'Не указана',
                        'brigade' => $training->employee->brigade?->name ?? 'Не указана',
                        'brigadeId' => $training->employee->brigade_id
                    ],
                    'assignedDate' => $training->assigned_date?->format('Y-m-d'),
                    'completedDate' => $training->completed_date?->format('Y-m-d'),
                    'expiresDate' => $training->expiration_date?->format('Y-m-d'),
                    'status' => $training->status,
                    'certificateUrl' => $training->certificate_file_path,
                    'certificateNumber' => $training->certificate_number,
                    'regulatoryActs' => $training->regulatory_acts,
                    'history' => $history,
                    'lastReminderSent' => $training->last_reminder_sent?->format('Y-m-d')
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Training not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch training details', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.4 GET /trainings/employee/{employeeId} - Обучения сотрудника
     */
    public function getEmployeeTrainings($employeeId)
    {
        try {
            $cacheKey = "employee_trainings_{$employeeId}";
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($employeeId) {
                $employee = Employee::with(['position:id,name', 'brigade:id,name'])->findOrFail($employeeId);
                
                $trainings = EmployeeCourse::with(['course:id,name'])
                    ->where('employee_id', $employeeId)
                    ->orderBy('expiration_date', 'asc')
                    ->get()
                    ->map(fn($training) => $this->formatEmployeeTraining($training));
                
                $stats = [
                    'total' => $trainings->count(),
                    'active' => $trainings->where('status', 'active')->count(),
                    'expired' => $trainings->where('status', 'expired')->count(),
                    'expiring' => $trainings->where('status', 'expiring')->count(),
                    'compliance' => $trainings->where('status', 'expired')->count() === 0
                ];
                
                return [
                    'employeeId' => $employee->id,
                    'employeeName' => $employee->full_name,
                    'employeePosition' => $employee->position?->name ?? 'Не указана',
                    'brigade' => $employee->brigade?->name ?? 'Не указана',
                    'trainings' => $trainings,
                    'statistics' => $stats
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch employee trainings', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.5 GET /trainings/brigade/{brigadeId} - Обучения по бригаде
     */
    public function getBrigadeTrainings($brigadeId)
    {
        try {
            $cacheKey = "brigade_trainings_{$brigadeId}";
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($brigadeId) {
                $brigade = Brigade::findOrFail($brigadeId);
                
                $employees = Employee::with(['position', 'employeeCourses.course'])
                    ->where('brigade_id', $brigadeId)
                    ->get();
                
                $result = [];
                foreach ($employees as $employee) {
                    $totalCourses = $employee->employeeCourses->count();
                    $compliantCourses = $employee->employeeCourses
                        ->whereNotIn('status', ['expired'])
                        ->count();
                    
                    $compliance = $totalCourses > 0 ? round(($compliantCourses / $totalCourses) * 100) : 0;
                    
                    $trainings = $employee->employeeCourses->map(function($training) {
                        return [
                            'id' => $training->id,
                            'name' => $training->course?->name,
                            'status' => $training->status,
                            'assignedDate' => $training->assigned_date?->format('Y-m-d'),
                            'expiresDate' => $training->expiration_date?->format('Y-m-d'),
                            'daysLeft' => $training->expiration_date ? now()->diffInDays($training->expiration_date, false) : null
                        ];
                    });
                    
                    $result[] = [
                        'employeeId' => $employee->id,
                        'name' => $employee->full_name,
                        'position' => $employee->position?->name ?? 'Не указана',
                        'compliance' => $compliance,
                        'trainings' => $trainings,
                        'totalTrainings' => $totalCourses,
                        'expiredCount' => $employee->employeeCourses->where('status', 'expired')->count()
                    ];
                }
                
                return [
                    'brigadeId' => $brigade->id,
                    'brigadeName' => $brigade->name,
                    'totalEmployees' => $employees->count(),
                    'employees' => $result
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Brigade not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch brigade trainings', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.6 PUT /trainings/{id}/complete - Отметить как пройденное
     */
    public function completeTraining($id, Request $request)
    {
        try {
            $training = EmployeeCourse::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'certificate_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'certificate_number' => 'nullable|string|max:100',
                'regulatory_acts' => 'nullable|string',
                'completed_date' => 'nullable|date'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            $certificatePath = null;
            if ($request->hasFile('certificate_file')) {
                $certificatePath = $request->file('certificate_file')->store('certificates', 'public');
            } elseif ($request->has('certificate_path')) {
                $certificatePath = $request->certificate_path;
            }
            
            $course = $training->course;
            $periodicityMonths = $course?->periodicity_months ?? 12;
            
            $completedDate = $request->has('completed_date')
                ? Carbon::parse($request->completed_date)
                : now();
            
            $newExpirationDate = $completedDate->copy()->addMonths($periodicityMonths);
            
            $training->status = 'active';
            $training->completed_date = $completedDate;
            $training->expiration_date = $newExpirationDate;
            $training->certificate_file_path = $certificatePath;
            
            if ($request->has('certificate_number')) {
                $training->certificate_number = $request->certificate_number;
            }
            
            if ($request->has('regulatory_acts')) {
                $training->regulatory_acts = $request->regulatory_acts;
            }
            
            $training->save();
            
            DB::commit();
            
            // Очищаем кэш после изменения
            $this->clearTrainingCache($training->employee_id, $training->course_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Обучение отмечено как пройденное',
                'completedDate' => $completedDate->format('Y-m-d'),
                'newExpiresDate' => $newExpirationDate->format('Y-m-d'),
                'certificate_number' => $training->certificate_number,
                'regulatory_acts' => $training->regulatory_acts
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Training not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Complete training error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to complete training', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.7 PUT /trainings/{id}/extend - Продлить обучение
     */
    public function extendTraining($id, Request $request)
    {
        try {
            $training = EmployeeCourse::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'months' => 'nullable|integer|min:1|max:60',
                'new_expiration_date' => 'nullable|date|after:today'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $oldExpirationDate = $training->expiration_date;
            
            if ($request->has('new_expiration_date')) {
                $newExpirationDate = Carbon::parse($request->new_expiration_date);
            } else {
                $months = $request->get('months', 12);
                $newExpirationDate = now()->addMonths($months);
            }
            
            $training->expiration_date = $newExpirationDate;
            $training->status = 'active';
            $training->save();
            
            $daysLeft = now()->diffInDays($newExpirationDate, false);
            
            $formattedOldDate = null;
            if ($oldExpirationDate) {
                $formattedOldDate = $oldExpirationDate instanceof Carbon 
                    ? $oldExpirationDate->format('Y-m-d')
                    : Carbon::parse($oldExpirationDate)->format('Y-m-d');
            }
            
            // Очищаем кэш после изменения
            $this->clearTrainingCache($training->employee_id, $training->course_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Срок продлен',
                'newExpiresDate' => $newExpirationDate->format('Y-m-d'),
                'daysLeft' => max(0, $daysLeft),
                'oldExpiresDate' => $formattedOldDate
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Training not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Extend training error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to extend training', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.8 POST /trainings/assign - Назначить обучение
     */
    public function assignTraining(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'course_id' => 'required|exists:courses,id',
                'assigned_date' => 'nullable|date',
                'expiration_date' => 'nullable|date|after:assigned_date'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $existing = EmployeeCourse::where('employee_id', $request->employee_id)
                ->where('course_id', $request->course_id)
                ->first();
            
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Обучение уже назначено сотруднику',
                    'trainingId' => $existing->id
                ], 400);
            }
            
            $course = Course::find($request->course_id);
            
            $assignedDate = $request->has('assigned_date') 
                ? Carbon::parse($request->assigned_date) 
                : now();
            
            $expirationDate = null;
            if ($request->has('expiration_date')) {
                $expirationDate = Carbon::parse($request->expiration_date);
            } elseif ($course && $course->periodicity_months) {
                $expirationDate = $assignedDate->copy()->addMonths($course->periodicity_months);
            }
            
            $training = EmployeeCourse::create([
                'employee_id' => $request->employee_id,
                'course_id' => $request->course_id,
                'status' => 'active',
                'assigned_date' => $assignedDate,
                'expiration_date' => $expirationDate,
                'last_reminder_sent' => null
            ]);
            
            $needsAttention = false;
            if ($expirationDate) {
                $daysLeft = now()->diffInDays($expirationDate, false);
                $needsAttention = $daysLeft <= 30 && $daysLeft >= 0;
            }
            
            $formattedExpirationDate = $expirationDate ? $expirationDate->format('Y-m-d') : null;
            
            // Очищаем кэш после создания
            $this->clearTrainingCache($request->employee_id, $request->course_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Обучение назначено',
                'trainingId' => $training->id,
                'employeeId' => $training->employee_id,
                'expiresDate' => $formattedExpirationDate,
                'needsAttention' => $needsAttention
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Assign training error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to assign training', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.9 POST /trainings/bulk-assign - Массовое назначение
     */
    public function bulkAssign(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:employees,id',
                'course_id' => 'required|exists:courses,id',
                'assigned_date' => 'nullable|date'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $course = Course::find($request->course_id);
            $assignedDate = $request->get('assigned_date', now());
            $expirationDate = $course && $course->periodicity_months 
                ? now()->addMonths($course->periodicity_months)
                : null;
            
            $assigned = 0;
            $failed = 0;
            $details = [];
            $affectedEmployeeIds = [];
            
            foreach ($request->employee_ids as $employeeId) {
                try {
                    $existing = EmployeeCourse::where('employee_id', $employeeId)
                        ->where('course_id', $request->course_id)
                        ->first();
                    
                    if ($existing) {
                        $details[] = [
                            'employeeId' => $employeeId,
                            'trainingId' => $existing->id,
                            'status' => 'already_assigned'
                        ];
                        $failed++;
                        continue;
                    }
                    
                    $training = EmployeeCourse::create([
                        'employee_id' => $employeeId,
                        'course_id' => $request->course_id,
                        'status' => 'active',
                        'assigned_date' => $assignedDate,
                        'expiration_date' => $expirationDate
                    ]);
                    
                    $details[] = [
                        'employeeId' => $employeeId,
                        'trainingId' => $training->id,
                        'status' => 'assigned'
                    ];
                    $assigned++;
                    $affectedEmployeeIds[] = $employeeId;
                    
                } catch (\Exception $e) {
                    $details[] = [
                        'employeeId' => $employeeId,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                    $failed++;
                }
            }
            
            // Очищаем кэш для всех затронутых сотрудников
            foreach ($affectedEmployeeIds as $employeeId) {
                $this->clearTrainingCache($employeeId, $request->course_id);
            }
            
            return response()->json([
                'success' => true,
                'assigned' => $assigned,
                'failed' => $failed,
                'details' => $details,
                'message' => "Назначено: {$assigned}, Пропущено: {$failed}"
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to bulk assign trainings', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.10 GET /trainings/statistics - Статистика по обучениям
     */
    public function getStatistics()
    {
        try {
            $cacheKey = 'trainings_statistics';
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->longCacheTTL), function() {
                $today = now();
                $expiring30Date = now()->addDays(30);
                $expiring60Date = now()->addDays(60);
                
                $expired = EmployeeCourse::where('status', 'expired')->whereNotNull('expiration_date')->where('expiration_date', '<', $today)->count();
                $expiring30 = EmployeeCourse::where('status', 'active')->whereNotNull('expiration_date')->whereBetween('expiration_date', [$today, $expiring30Date])->count();
                $expiring60 = EmployeeCourse::where('status', 'active')->whereNotNull('expiration_date')->whereBetween('expiration_date', [$expiring30Date, $expiring60Date])->count();
                $active = EmployeeCourse::where('status', 'active')->where(fn($q) => $q->whereNull('expiration_date')->orWhere('expiration_date', '>', $today))->count();
                $required = EmployeeCourse::count();
                $noData = Employee::whereDoesntHave('employeeCourses')->count();
                
                return [
                    'expired' => $expired,
                    'expiring30' => $expiring30,
                    'expiring60' => $expiring60,
                    'active' => $active,
                    'required' => $required,
                    'noData' => $noData,
                    'totalEmployees' => Employee::count(),
                    'totalCourses' => Course::count()
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch statistics', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.11 GET /trainings/export - Экспорт списка
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'csv');
            $status = $request->get('status');
            
            $query = EmployeeCourse::with(['employee', 'course', 'employee.position', 'employee.brigade']);
            
            if ($status) {
                if ($status === 'expired') {
                    $query->where('status', 'expired')->where('expiration_date', '<', now());
                } elseif ($status === 'expiring') {
                    $query->where('status', 'active')->whereNotNull('expiration_date')->whereBetween('expiration_date', [now(), now()->addDays(30)]);
                } else {
                    $query->where('status', $status);
                }
            }
            
            $trainings = $query->get();
            
            $data = $trainings->map(function($training) {
                return [
                    'ID' => $training->id,
                    'Сотрудник' => $training->employee->full_name,
                    'Должность' => $training->employee->position?->name ?? '',
                    'Бригада' => $training->employee->brigade?->name ?? '',
                    'Обучение' => $training->course?->name ?? '',
                    'Статус' => $training->status,
                    'Дата назначения' => $training->assigned_date?->format('Y-m-d'),
                    'Дата завершения' => $training->completed_date?->format('Y-m-d'),
                    'Дата истечения' => $training->expiration_date?->format('Y-m-d'),
                    'Дней до истечения' => $training->expiration_date ? now()->diffInDays($training->expiration_date, false) : null
                ];
            });
            
            if ($format === 'csv') {
                $fileName = 'trainings_export_' . date('Y-m-d_His') . '.csv';
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                ];
                
                $callback = function() use ($data) {
                    $file = fopen('php://output', 'w');
                    if ($data->isNotEmpty()) {
                        fputcsv($file, array_keys($data->first()));
                    }
                    foreach ($data as $row) {
                        fputcsv($file, $row);
                    }
                    fclose($file);
                };
                
                return response()->stream($callback, 200, $headers);
            }
            
            return response()->json(['message' => 'Excel export not implemented yet. Use CSV format.', 'data' => $data], 200);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to export trainings', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 2.12 GET /trainings/search - Поиск обучений
     */
    public function search(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $query = $request->get('query', '');
            
            $cacheKey = "trainings_search_" . md5($query . $page . $limit);
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($query, $page, $limit) {
                $trainingsQuery = EmployeeCourse::with(['employee', 'course', 'employee.position', 'employee.brigade'])
                    ->whereHas('employee', fn($q) => $q->where('full_name', 'LIKE', "%{$query}%"))
                    ->orWhereHas('course', fn($q) => $q->where('name', 'LIKE', "%{$query}%"));
                
                $total = $trainingsQuery->count();
                $hasMore = ($page * $limit) < $total;
                
                $trainings = $trainingsQuery->skip(($page - 1) * $limit)->take($limit)->get()
                    ->map(fn($training) => [
                        'id' => $training->id,
                        'employeeName' => $training->employee->full_name,
                        'employeeId' => $training->employee_id,
                        'trainingName' => $training->course?->name,
                        'trainingId' => $training->course_id,
                        'status' => $training->status,
                        'expiresDate' => $training->expiration_date?->format('Y-m-d'),
                        'brigade' => $training->employee->brigade?->name
                    ]);
                
                return compact('trainings', 'total', 'page', 'limit', 'hasMore');
            });
            
            return response()->json($result, 200);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Search failed', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * PUT /trainings/{id}/certificate - Обновление информации о сертификате
     */
    public function updateCertificateInfo(Request $request, $id)
    {
        try {
            $training = EmployeeCourse::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'certificate_number' => 'nullable|string|max:100',
                'regulatory_acts' => 'nullable|string',
                'certificate_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'completed_date' => 'nullable|date'
            ]);
            
            if ($validator->fails()) {
                return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            
            DB::beginTransaction();
            
            if ($request->has('certificate_number')) {
                $training->certificate_number = $request->certificate_number;
            }
            
            if ($request->has('regulatory_acts')) {
                $training->regulatory_acts = $request->regulatory_acts;
            }
            
            if ($request->has('completed_date')) {
                $completedDate = Carbon::parse($request->completed_date);
                $training->completed_date = $completedDate;
                $course = $training->course;
                $periodicityMonths = $course?->periodicity_months ?? 12;
                $newExpirationDate = $completedDate->copy()->addMonths($periodicityMonths);
                $training->expiration_date = $newExpirationDate;
                $training->status = 'active';
            }
            
            if ($request->hasFile('certificate_file')) {
                if ($training->certificate_file_path && \Storage::disk('public')->exists($training->certificate_file_path)) {
                    \Storage::disk('public')->delete($training->certificate_file_path);
                }
                $certificatePath = $request->file('certificate_file')->store('certificates', 'public');
                $training->certificate_file_path = $certificatePath;
            }
            
            $training->save();
            
            DB::commit();
            
            // Очищаем кэш после изменения
            $this->clearTrainingCache($training->employee_id, $training->course_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Информация о сертификате обновлена',
                'data' => [
                    'id' => $training->id,
                    'certificate_number' => $training->certificate_number,
                    'regulatory_acts' => $training->regulatory_acts,
                    'certificate_url' => $training->certificate_file_path ? asset('storage/' . $training->certificate_file_path) : null,
                    'completed_date' => $training->completed_date?->format('Y-m-d'),
                    'expiration_date' => $training->expiration_date?->format('Y-m-d')
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Training not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Update certificate error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update certificate info', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * PATCH /trainings/{id}/certificate-number - Обновление только номера удостоверения
     */
    public function updateCertificateNumber(Request $request, $id)
    {
        try {
            $training = EmployeeCourse::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'certificate_number' => 'required|string|max:100'
            ]);
            
            if ($validator->fails()) {
                return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            
            $training->certificate_number = $request->certificate_number;
            $training->save();
            
            // Очищаем кэш после изменения
            $this->clearTrainingCache($training->employee_id, $training->course_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Номер удостоверения обновлен',
                'data' => [
                    'id' => $training->id,
                    'certificate_number' => $training->certificate_number,
                    'employee_name' => $training->employee->full_name,
                    'course_name' => $training->course?->name
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Training not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update certificate number', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * PATCH /trainings/{id}/regulatory-acts - Обновление только НПА
     */
    public function updateRegulatoryActs(Request $request, $id)
    {
        try {
            $training = EmployeeCourse::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'regulatory_acts' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            
            $training->regulatory_acts = $request->regulatory_acts;
            $training->save();
            
            // Очищаем кэш после изменения
            $this->clearTrainingCache($training->employee_id, $training->course_id);
            
            return response()->json([
                'success' => true,
                'message' => 'НПА обновлен',
                'data' => [
                    'id' => $training->id,
                    'regulatory_acts' => $training->regulatory_acts,
                    'employee_name' => $training->employee->full_name,
                    'course_name' => $training->course?->name
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Training not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update regulatory acts', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * DELETE /trainings/{id} - Удаление обучения
     */
    public function destroy($id)
    {
        try {
            $training = EmployeeCourse::findOrFail($id);
            $employeeId = $training->employee_id;
            $courseId = $training->course_id;
            
            $training->delete();
            
            // Очищаем кэш после удаления
            $this->clearTrainingCache($employeeId, $courseId);
            
            return response()->json([
                'success' => true,
                'message' => 'Обучение удалено'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Training not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete training', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Очистка кэша после изменений
     */
    private function clearTrainingCache($employeeId, $courseId = null)
    {
        // Очищаем общую статистику
        Cache::forget('trainings_statistics');
        Cache::forget('event_mapping');  // ← ВАЖНО: очищаем маппинг мероприятий
        
        // Очищаем кэш конкретного сотрудника
        if ($employeeId) {
            Cache::forget("employee_trainings_{$employeeId}");
        }
        
        // Очищаем кэш деталей обучения
        Cache::forget("training_details_*");
        
        // Очищаем кэш бригад
        Cache::forget("brigade_trainings_*");
        
        // Очищаем кэш поиска
        Cache::forget("trainings_search_*");
        
        // Очищаем кэш просроченных и истекающих
        Cache::forget("trainings_expired_*");
        Cache::forget("trainings_expiring_*");
        Cache::forget("trainings_summary_*");
    }
    

    /**
     * Получить маппинг мероприятий (кэшированный)
     */
    private function getEventMapping()
    {
        // Уменьшаем время кэширования до 1 минуты для теста
        return Cache::remember('event_mapping', now()->addMinutes(1), function() {
            $mapping = [];
            $events = TrainingEvent::with(['participants'])->get(['id', 'course_id', 'title', 'start_date', 'status']);
            
            foreach ($events as $event) {
                foreach ($event->participants as $participant) {
                    $mapping[$event->course_id][$participant->employee_id] = [
                        'event_id' => $event->id,
                        'event_title' => $event->title,
                        'event_start_date' => $event->start_date->format('Y-m-d'),
                        'event_status' => $event->status,
                        'participant_status' => $participant->status
                    ];
                }
            }
            return $mapping;
        });
    }
    
    /**
     * Группировка по курсам
     */
    private function groupByCourse($items)
    {
        return collect($items)->groupBy('course_id')->map(function($items, $courseId) {
            $first = $items->first();
            return [
                'course_id' => $courseId,
                'course_name' => $first['course_name'],
                'course_category' => $first['course_category'],
                'count' => $items->count(),
                'registered_count' => $items->where('registered_for_event', true)->count(),
                'not_registered_count' => $items->where('registered_for_event', false)->count(),
                'employees' => collect($items)->map(fn($i) => [
                    'employee_id' => $i['employee_id'],
                    'employee_name' => $i['employee_name'],
                    'personnel_number' => $i['personnel_number'],
                    'position' => $i['position'],
                    'position_id' => $i['position_id'],
                    'department' => $i['department'],
                    'department_id' => $i['department_id'],
                    'training_id' => $i['training_id'], // Добавлено поле training_id
                    'days_left' => $i['days_left'] ?? null,
                    'days_overdue' => $i['days_overdue'] ?? null,
                    'expiration_date' => $i['expiration_date'],
                    'registered_for_event' => $i['registered_for_event'],
                    'event' => $i['event']
                ])->values()->toArray()
            ];
        })->values()->toArray();
    }
    /**
     * Расчет статистики
     */
    private function calculateStatistics($allTrainings, $expired, $expiringMonth, $expiringTwoMonths)
    {
        return [
            'total_trainings' => $allTrainings->count(),
            'expired_total' => count($expired),
            'expiring_month_total' => count($expiringMonth),
            'expiring_two_months_total' => count($expiringTwoMonths),
            'unique_employees_with_expired' => collect($expired)->unique('employee_id')->count(),
            'unique_employees_expiring_month' => collect($expiringMonth)->unique('employee_id')->count(),
            'unique_employees_expiring_two_months' => collect($expiringTwoMonths)->unique('employee_id')->count(),
            'registered_for_events' => [
                'expired' => collect($expired)->where('registered_for_event', true)->count(),
                'expiring_month' => collect($expiringMonth)->where('registered_for_event', true)->count(),
                'expiring_two_months' => collect($expiringTwoMonths)->where('registered_for_event', true)->count()
            ]
        ];
    }
    
    /**
     * Форматирование элемента обучения
     */
    private function formatTrainingItem($training, $employee, $course, $eventInfo, $daysLeft)
    {
        $fullName = trim(implode(' ', array_filter([$employee->last_name, $employee->first_name, $employee->middle_name])));
        
        return [
            'employee_id' => $employee->id,
            'employee_name' => $fullName ?: $employee->full_name,
            'personnel_number' => $employee->personnel_number,
            'position' => $employee->position?->name ?? 'Не указана',
            'position_id' => $employee->position_id,
            'department' => $employee->department?->name ?? 'Не указано',
            'department_id' => $employee->department_id,
            'training_id' => $training->id, // ← Добавлено поле training_id
            'course_id' => $course->id,
            'course_name' => $course->name ?? 'Неизвестный курс',
            'course_category' => $course->category?->name,
            'assigned_date' => $training->assigned_date?->format('Y-m-d'),
            'completed_date' => $training->completed_date?->format('Y-m-d'),
            'expiration_date' => $training->expiration_date->format('Y-m-d'),
            'days_left' => $daysLeft >= 0 ? (int)$daysLeft : 0, // ← Округление
            'status' => $training->status,
            'certificate_number' => $training->certificate_number,
            'registered_for_event' => $eventInfo !== null,
            'event' => $eventInfo
        ];
    }
        
        private function formatExpiredTraining($training)
    {
        $daysOverdue = now()->diffInDays($training->expiration_date, false);
        
        return [
            'id' => $training->id,
            'employeeName' => $training->employee->full_name,
            'employeePosition' => $training->employee->position?->name ?? 'Не указана',
            'employeeId' => $training->employee_id,
            'trainingName' => $training->course?->name ?? 'Неизвестный курс',
            'trainingId' => $training->course_id,
            'expiredDate' => $training->expiration_date?->format('Y-m-d'),
            'daysOverdue' => abs((int)$daysOverdue), // ← Добавлено приведение к int
            'brigade' => $training->employee->brigade?->name ?? 'Не указана',
            'brigadeId' => $training->employee->brigade_id,
            'status' => $training->status
        ];
    }
    
    /**
     * Форматирование истекающего обучения
     */
    private function formatExpiringTraining($training, $today)
    {
        $daysLeft = $today->diffInDays($training->expiration_date, false);
        
        return [
            'id' => $training->id,
            'employeeName' => $training->employee->full_name,
            'employeePosition' => $training->employee->position?->name ?? 'Не указана',
            'employeeId' => $training->employee_id,
            'trainingName' => $training->course?->name ?? 'Неизвестный курс',
            'trainingId' => $training->course_id,
            'expiresDate' => $training->expiration_date?->format('Y-m-d'),
            'daysLeft' => max(0, $daysLeft),
            'brigade' => $training->employee->brigade?->name ?? 'Не указана',
            'brigadeId' => $training->employee->brigade_id,
            'status' => $this->getExpiryStatus($daysLeft)
        ];
    }
    
    /**
     * Форматирование обучения сотрудника
     */
    private function formatEmployeeTraining($training)
    {
        return [
            'id' => $training->id,
            'name' => $training->course?->name ?? 'Неизвестный курс',
            'courseId' => $training->course_id,
            'status' => $training->status,
            'assignedDate' => $training->assigned_date?->format('Y-m-d'),
            'completedDate' => $training->completed_date?->format('Y-m-d'),
            'expiresDate' => $training->expiration_date?->format('Y-m-d'),
            'daysLeft' => $training->expiration_date ? now()->diffInDays($training->expiration_date, false) : null,
            'certificateUrl' => $training->certificate_file_path,
            'certificateNumber' => $training->certificate_number,
            'regulatoryActs' => $training->regulatory_acts
        ];
    }
    
    /**
     * Получить ключ кэша
     */
    private function getCacheKey($prefix, $request)
    {
        $params = array_merge(
            $request->only(['brigade_id', 'department_id', 'position_id', 'employee_id', 'search']),
            ['page' => $request->get('page', 1), 'limit' => $request->get('limit', 20)]
        );
        return "trainings_{$prefix}_" . md5(json_encode($params));
    }
    
    /**
     * Получить статус истечения
     */
    private function getExpiryStatus($daysLeft)
    {
        if ($daysLeft <= 0) return 'expired';
        if ($daysLeft <= 7) return 'critical';
        if ($daysLeft <= 14) return 'urgent';
        if ($daysLeft <= 30) return 'warning';
        return 'active';
    }
    
    /**
     * Получить историю обучения
     */
    private function getTrainingHistory($training)
    {
        $history = [];
        
        if ($training->assigned_date) {
            $history[] = [
                'action' => 'assigned',
                'date' => $training->assigned_date->format('Y-m-d H:i:s'),
                'user' => 'Система'
            ];
        }
        
        if ($training->completed_date) {
            $history[] = [
                'action' => 'completed',
                'date' => $training->completed_date->format('Y-m-d H:i:s'),
                'user' => 'Система'
            ];
        }
        
        if ($training->last_reminder_sent) {
            $history[] = [
                'action' => 'reminder_sent',
                'date' => $training->last_reminder_sent->format('Y-m-d H:i:s'),
                'user' => 'Система'
            ];
        }
        
        return $history;
    }

   /**
     * GET /trainings/employee-courses-summary - Курсы всех сотрудников по срокам
     */
    public function getEmployeeCoursesSummary(Request $request)
    {
        try {
            $cacheKey = $this->getCacheKey('summary', $request);
            
            $result = Cache::remember($cacheKey, now()->addMinutes($this->cacheTTL), function() use ($request) {
                $today = now();
                
                // Оптимизированный запрос с агрегацией
                $query = EmployeeCourse::with([
                    'employee:id,full_name,last_name,first_name,middle_name,personnel_number,position_id,department_id',
                    'employee.position:id,name',
                    'employee.department:id,name',
                    'course:id,name,category_id',
                    'course.category:id,name'
                ])->select(['id', 'employee_id', 'course_id', 'status', 'assigned_date', 'completed_date', 'expiration_date', 'certificate_number']);
                
                if ($request->filled('department_id')) {
                    $query->whereHas('employee', fn($q) => $q->where('department_id', $request->department_id));
                }
                if ($request->filled('position_id')) {
                    $query->whereHas('employee', fn($q) => $q->where('position_id', $request->position_id));
                }
                if ($request->filled('brigade_id')) {
                    $query->whereHas('employee', fn($q) => $q->where('brigade_id', $request->brigade_id));
                }
                if ($request->filled('employee_id')) {
                    $query->where('employee_id', $request->employee_id);
                }
                
                $allTrainings = $query->get();
                
                // Получаем маппинг мероприятий
                $eventMapping = $this->getEventMapping();
                
                // Разделяем по категориям
                $expired = [];
                $expiringMonth = [];
                $expiringTwoMonths = [];
                
                foreach ($allTrainings as $training) {
                    $employee = $training->employee;
                    $course = $training->course;
                    $expirationDate = $training->expiration_date;
                    
                    if (!$expirationDate) continue;
                    
                    $daysLeft = $today->diffInDays($expirationDate, false);
                    $eventInfo = $eventMapping[$course->id][$employee->id] ?? null;
                    
                    $item = $this->formatTrainingItem($training, $employee, $course, $eventInfo, $daysLeft);
                    // Добавляем training_id в каждый элемент
                    $item['training_id'] = $training->id;
                    
                    if ($daysLeft < 0) {
                        $item['days_overdue'] = abs((int)$daysLeft);
                        $expired[] = $item;
                    } elseif ($daysLeft <= 30) {
                        $expiringMonth[] = $item;
                    } elseif ($daysLeft <= 60) {
                        $expiringTwoMonths[] = $item;
                    }
                }
                
                return [
                    'expired' => [
                        'total' => count($expired),
                        'by_course' => $this->groupByCourse($expired)
                    ],
                    'expiring_in_month' => [
                        'total' => count($expiringMonth),
                        'by_course' => $this->groupByCourse($expiringMonth)
                    ],
                    'expiring_in_two_months' => [
                        'total' => count($expiringTwoMonths),
                        'by_course' => $this->groupByCourse($expiringTwoMonths)
                    ],
                    'statistics' => $this->calculateStatistics($allTrainings, $expired, $expiringMonth, $expiringTwoMonths)
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (\Exception $e) {
            \Log::error('Employee courses summary error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch employee courses summary', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /training-events/clear-cache - Очистка кэша мероприятий
     */
    public function clearCache()
    {
        Cache::forget('event_mapping');
        Cache::forget('trainings_statistics');
        
        return response()->json([
            'success' => true,
            'message' => 'Training events cache cleared'
        ], 200);
    }
}
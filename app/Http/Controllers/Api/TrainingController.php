<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCourse;
use App\Models\Employee;
use App\Models\Course;
use App\Models\Brigade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrainingController extends Controller
{
    /**
     * 2.1 GET /trainings/expired - Просроченные обучения
     */
    public function getExpiredTrainings(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            
            $query = EmployeeCourse::with(['employee.position', 'employee.brigade', 'course'])
                ->where('status', 'expired')
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<', now())
                ->orderBy('expiration_date', 'asc');
            
            // Фильтр по бригаде
            if ($request->has('brigade_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            // Поиск по сотруднику
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('employee', function($q) use ($search) {
                    $q->where('full_name', 'LIKE', "%{$search}%");
                });
            }
            
            $total = $query->count();
            $hasMore = ($page * $limit) < $total;
            
            $trainings = $query->skip(($page - 1) * $limit)
                               ->take($limit)
                               ->get()
                               ->map(function($training) {
                                   $daysOverdue = now()->diffInDays($training->expiration_date, false);
                                   
                                   return [
                                       'id' => $training->id,
                                       'employeeName' => $training->employee->full_name,
                                       'employeePosition' => $training->employee->position?->name ?? 'Не указана',
                                       'employeeId' => $training->employee_id,
                                       'trainingName' => $training->course?->name ?? 'Неизвестный курс',
                                       'trainingId' => $training->course_id,
                                       'expiredDate' => $training->expiration_date?->format('Y-m-d'),
                                       'daysOverdue' => abs($daysOverdue),
                                       'brigade' => $training->employee->brigade?->name ?? 'Не указана',
                                       'brigadeId' => $training->employee->brigade_id,
                                       'status' => $training->status
                                   ];
                               });
            
            return response()->json([
                'trainings' => $trainings,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'hasMore' => $hasMore
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch expired trainings',
                'message' => $e->getMessage()
            ], 500);
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
            
            $query = EmployeeCourse::with(['employee.position', 'employee.brigade', 'course'])
                ->where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$today, $expiryDate])
                ->orderBy('expiration_date', 'asc');
            
            // Фильтр по бригаде
            if ($request->has('brigade_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            // Фильтр по сотруднику
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            
            $total = $query->count();
            $hasMore = ($page * $limit) < $total;
            
            $trainings = $query->skip(($page - 1) * $limit)
                               ->take($limit)
                               ->get()
                               ->map(function($training) use ($today) {
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
                               });
            
            return response()->json([
                'trainings' => $trainings,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'hasMore' => $hasMore
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch expiring trainings',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 2.3 GET /trainings/{id} - Детали обучения
     */
   public function show($id)
    {
        try {
            $training = EmployeeCourse::with(['employee.position', 'employee.brigade', 'course'])
                ->findOrFail($id);
            
            // История изменений
            $history = $this->getTrainingHistory($training);
            
            $response = [
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
                'certificateNumber' => $training->certificate_number,      // Добавлено
                'regulatoryActs' => $training->regulatory_acts,            // Добавлено
                'history' => $history,
                'lastReminderSent' => $training->last_reminder_sent?->format('Y-m-d')
            ];
            
            return response()->json($response, 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Training not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch training details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 2.4 GET /trainings/employee/{employeeId} - Обучения сотрудника
     */
    public function getEmployeeTrainings($employeeId)
    {
        try {
            $employee = Employee::with(['position', 'brigade'])->findOrFail($employeeId);
            
            $trainings = EmployeeCourse::with(['course'])
                ->where('employee_id', $employeeId)
                ->orderBy('expiration_date', 'asc')
                ->get()
                ->map(function($training) {
                    return [
                        'id' => $training->id,
                        'name' => $training->course?->name ?? 'Неизвестный курс',
                        'courseId' => $training->course_id,
                        'status' => $training->status,
                        'assignedDate' => $training->assigned_date?->format('Y-m-d'),
                        'completedDate' => $training->completed_date?->format('Y-m-d'),
                        'expiresDate' => $training->expiration_date?->format('Y-m-d'),
                        'daysLeft' => $training->expiration_date 
                            ? now()->diffInDays($training->expiration_date, false) 
                            : null,
                        'certificateUrl' => $training->certificate_file_path,
                        'certificateNumber' => $training->certificate_number,    // Добавлено
                        'regulatoryActs' => $training->regulatory_acts           // Добавлено
                    ];
                });
            
            // Статистика по обучениям сотрудника
            $stats = [
                'total' => $trainings->count(),
                'active' => $trainings->where('status', 'active')->count(),
                'expired' => $trainings->where('status', 'expired')->count(),
                'expiring' => $trainings->where('status', 'expiring')->count(),
                'compliance' => $trainings->where('status', 'expired')->count() === 0
            ];
            
            return response()->json([
                'employeeId' => $employee->id,
                'employeeName' => $employee->full_name,
                'employeePosition' => $employee->position?->name ?? 'Не указана',
                'brigade' => $employee->brigade?->name ?? 'Не указана',
                'trainings' => $trainings,
                'statistics' => $stats
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Employee not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch employee trainings',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 2.5 GET /trainings/brigade/{brigadeId} - Обучения по бригаде
     */
    public function getBrigadeTrainings($brigadeId)
    {
        try {
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
                
                $compliance = $totalCourses > 0 
                    ? round(($compliantCourses / $totalCourses) * 100) 
                    : 0;
                
                $trainings = $employee->employeeCourses->map(function($training) {
                    return [
                        'id' => $training->id,
                        'name' => $training->course?->name,
                        'status' => $training->status,
                        'assignedDate' => $training->assigned_date?->format('Y-m-d'),
                        'expiresDate' => $training->expiration_date?->format('Y-m-d'),
                        'daysLeft' => $training->expiration_date 
                            ? now()->diffInDays($training->expiration_date, false) 
                            : null
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
            
            return response()->json([
                'brigadeId' => $brigade->id,
                'brigadeName' => $brigade->name,
                'totalEmployees' => $employees->count(),
                'employees' => $result
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Brigade not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch brigade trainings',
                'message' => $e->getMessage()
            ], 500);
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
                'certificate_number' => 'nullable|string|max:100',  // Добавлено
                'regulatory_acts' => 'nullable|string',             // Добавлено
                'completed_date' => 'nullable|date'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Загружаем сертификат если есть
            $certificatePath = null;
            if ($request->hasFile('certificate_file')) {
                $certificatePath = $request->file('certificate_file')
                    ->store('certificates', 'public');
            } elseif ($request->has('certificate_path')) {
                $certificatePath = $request->certificate_path;
            }
            
            // Рассчитываем новую дату истечения
            $course = $training->course;
            $periodicityMonths = $course?->periodicity_months ?? 12;
            
            // Преобразуем дату завершения
            $completedDate = $request->has('completed_date')
                ? \Carbon\Carbon::parse($request->completed_date)
                : now();
            
            $newExpirationDate = $completedDate->copy()->addMonths($periodicityMonths);
            
            // Обновляем запись
            $training->status = 'active';
            $training->completed_date = $completedDate;
            $training->expiration_date = $newExpirationDate;
            $training->certificate_file_path = $certificatePath;
            
            // Добавляем новые поля
            if ($request->has('certificate_number')) {
                $training->certificate_number = $request->certificate_number;
            }
            
            if ($request->has('regulatory_acts')) {
                $training->regulatory_acts = $request->regulatory_acts;
            }
            
            $training->save();
            
            DB::commit();
            
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
            return response()->json([
                'success' => false,
                'message' => 'Training not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Complete training error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete training',
                'error' => $e->getMessage()
            ], 500);
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
            
            // Сохраняем старую дату до обновления
            $oldExpirationDate = $training->expiration_date;
            
            // Рассчитываем новую дату истечения
            if ($request->has('new_expiration_date')) {
                // Преобразуем строку в объект Carbon
                $newExpirationDate = \Carbon\Carbon::parse($request->new_expiration_date);
            } else {
                $months = $request->get('months', 12);
                $newExpirationDate = now()->addMonths($months);
            }
            
            // Обновляем запись
            $training->expiration_date = $newExpirationDate;
            $training->status = 'active';
            $training->save();
            
            // Рассчитываем количество дней до истечения
            $daysLeft = now()->diffInDays($newExpirationDate, false);
            
            // Форматируем даты для ответа, проверяя их наличие
            $formattedOldDate = null;
            if ($oldExpirationDate) {
                // Если oldExpirationDate уже Carbon объект, используем его, иначе парсим
                $formattedOldDate = $oldExpirationDate instanceof \Carbon\Carbon 
                    ? $oldExpirationDate->format('Y-m-d')
                    : \Carbon\Carbon::parse($oldExpirationDate)->format('Y-m-d');
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Срок продлен',
                'newExpiresDate' => $newExpirationDate->format('Y-m-d'),
                'daysLeft' => max(0, $daysLeft),
                'oldExpiresDate' => $formattedOldDate
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Training not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Extend training error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend training',
                'error' => $e->getMessage()
            ], 500);
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
            
            // Проверяем, нет ли уже назначения
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
            
            // Обработка дат - преобразуем строки в объекты Carbon
            $assignedDate = $request->has('assigned_date') 
                ? \Carbon\Carbon::parse($request->assigned_date) 
                : now();
            
            $expirationDate = null;
            if ($request->has('expiration_date')) {
                $expirationDate = \Carbon\Carbon::parse($request->expiration_date);
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
            
            // Проверяем, требует ли внимания (истекает в ближайшие 30 дней)
            $needsAttention = false;
            if ($expirationDate) {
                $daysLeft = now()->diffInDays($expirationDate, false);
                $needsAttention = $daysLeft <= 30 && $daysLeft >= 0;
            }
            
            // Форматируем даты для ответа
            $formattedExpirationDate = $expirationDate ? $expirationDate->format('Y-m-d') : null;
            
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign training',
                'error' => $e->getMessage()
            ], 500);
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
            
            foreach ($request->employee_ids as $employeeId) {
                try {
                    // Проверяем, нет ли уже назначения
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
                    
                } catch (\Exception $e) {
                    $details[] = [
                        'employeeId' => $employeeId,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                    $failed++;
                }
            }
            
            return response()->json([
                'success' => true,
                'assigned' => $assigned,
                'failed' => $failed,
                'details' => $details,
                'message' => "Назначено: {$assigned}, Пропущено: {$failed}"
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk assign trainings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 2.10 GET /trainings/statistics - Статистика по обучениям
     */
    public function getStatistics()
    {
        try {
            $today = now();
            $expiring30Date = now()->addDays(30);
            $expiring60Date = now()->addDays(60);
            
            $expired = EmployeeCourse::where('status', 'expired')
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<', $today)
                ->count();
            
            $expiring30 = EmployeeCourse::where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$today, $expiring30Date])
                ->count();
            
            $expiring60 = EmployeeCourse::where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$expiring30Date, $expiring60Date])
                ->count();
            
            $active = EmployeeCourse::where('status', 'active')
                ->where(function($q) use ($today) {
                    $q->whereNull('expiration_date')
                      ->orWhere('expiration_date', '>', $today);
                })
                ->count();
            
            $required = EmployeeCourse::count();
            
            $noData = Employee::whereDoesntHave('employeeCourses')->count();
            
            return response()->json([
                'expired' => $expired,
                'expiring30' => $expiring30,
                'expiring60' => $expiring60,
                'active' => $active,
                'required' => $required,
                'noData' => $noData,
                'totalEmployees' => Employee::count(),
                'totalCourses' => Course::count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch statistics',
                'message' => $e->getMessage()
            ], 500);
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
                    $query->where('status', 'expired')
                          ->where('expiration_date', '<', now());
                } elseif ($status === 'expiring') {
                    $query->where('status', 'active')
                          ->whereNotNull('expiration_date')
                          ->whereBetween('expiration_date', [now(), now()->addDays(30)]);
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
                    'Дней до истечения' => $training->expiration_date 
                        ? now()->diffInDays($training->expiration_date, false) 
                        : null
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
                    
                    // Заголовки
                    if ($data->isNotEmpty()) {
                        fputcsv($file, array_keys($data->first()));
                    }
                    
                    // Данные
                    foreach ($data as $row) {
                        fputcsv($file, $row);
                    }
                    
                    fclose($file);
                };
                
                return response()->stream($callback, 200, $headers);
                
            } else {
                // Excel формат (можно использовать Laravel Excel)
                return response()->json([
                    'message' => 'Excel export not implemented yet. Use CSV format.',
                    'data' => $data
                ], 200);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to export trainings',
                'message' => $e->getMessage()
            ], 500);
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
            
            $trainingsQuery = EmployeeCourse::with(['employee', 'course', 'employee.position', 'employee.brigade'])
                ->whereHas('employee', function($q) use ($query) {
                    $q->where('full_name', 'LIKE', "%{$query}%");
                })
                ->orWhereHas('course', function($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%");
                });
            
            $total = $trainingsQuery->count();
            $hasMore = ($page * $limit) < $total;
            
            $trainings = $trainingsQuery->skip(($page - 1) * $limit)
                                        ->take($limit)
                                        ->get()
                                        ->map(function($training) {
                                            return [
                                                'id' => $training->id,
                                                'employeeName' => $training->employee->full_name,
                                                'employeeId' => $training->employee_id,
                                                'trainingName' => $training->course?->name,
                                                'trainingId' => $training->course_id,
                                                'status' => $training->status,
                                                'expiresDate' => $training->expiration_date?->format('Y-m-d'),
                                                'brigade' => $training->employee->brigade?->name
                                            ];
                                        });
            
            return response()->json([
                'trainings' => $trainings,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'hasMore' => $hasMore
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /trainings/{id}/certificate - Обновление информации о сертификате (номер + НПА + файл)
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
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Обновляем номер удостоверения
            if ($request->has('certificate_number')) {
                $training->certificate_number = $request->certificate_number;
            }
            
            // Обновляем НПА
            if ($request->has('regulatory_acts')) {
                $training->regulatory_acts = $request->regulatory_acts;
            }
            
            // Обновляем дату завершения
            if ($request->has('completed_date')) {
                $completedDate = \Carbon\Carbon::parse($request->completed_date);
                $training->completed_date = $completedDate;
                
                // Пересчитываем дату истечения
                $course = $training->course;
                $periodicityMonths = $course?->periodicity_months ?? 12;
                $newExpirationDate = $completedDate->copy()->addMonths($periodicityMonths);
                $training->expiration_date = $newExpirationDate;
                $training->status = 'active';
            }
            
            // Загружаем файл сертификата
            if ($request->hasFile('certificate_file')) {
                // Удаляем старый файл если есть
                if ($training->certificate_file_path && \Storage::disk('public')->exists($training->certificate_file_path)) {
                    \Storage::disk('public')->delete($training->certificate_file_path);
                }
                
                $certificatePath = $request->file('certificate_file')->store('certificates', 'public');
                $training->certificate_file_path = $certificatePath;
            }
            
            $training->save();
            
            DB::commit();
            
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
            return response()->json([
                'success' => false,
                'message' => 'Training not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Update certificate error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update certificate info',
                'error' => $e->getMessage()
            ], 500);
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
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $training->certificate_number = $request->certificate_number;
            $training->save();
            
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
            return response()->json([
                'success' => false,
                'message' => 'Training not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Update certificate number error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update certificate number',
                'error' => $e->getMessage()
            ], 500);
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
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $training->regulatory_acts = $request->regulatory_acts;
            $training->save();
            
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
            return response()->json([
                'success' => false,
                'message' => 'Training not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Update regulatory acts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update regulatory acts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   /**
     * GET /trainings/dashboard/expired-by-course - Просроченные обучения по курсам (для карточки 🔴)
     * 
     * @param string|null period - month (за текущий месяц), two_months (за два месяца), null (все)
     */
    public function getExpiredByCourse(Request $request)
    {
        try {
            $period = $request->get('period'); // month, two_months, или null (все)
            $today = now();
            
            $query = EmployeeCourse::with(['course', 'employee'])
                ->where('status', 'expired')
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<', $today);
            
            // Фильтр по периоду
            if ($period === 'month') {
                // За текущий месяц (с начала месяца)
                $query->where('expiration_date', '>=', $today->copy()->startOfMonth());
            } elseif ($period === 'two_months') {
                // За два месяца
                $query->where('expiration_date', '>=', $today->copy()->subMonths(2)->startOfMonth());
            }
            
            // Фильтр по бригаде
            if ($request->has('brigade_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            // Фильтр по подразделению
            if ($request->has('department_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
            
            // Фильтр по должности
            if ($request->has('position_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('position_id', $request->position_id);
                });
            }
            
            // Получаем все записи
            $expiredRecords = $query->get();
            
            // Группируем по курсам
            $expiredByCourse = $expiredRecords
                ->groupBy('course_id')
                ->map(function($items, $courseId) {
                    $course = $items->first()->course;
                    $uniqueEmployees = $items->unique('employee_id');
                    
                    // Получаем минимальную дату просрочки
                    $minExpiredDate = $items->min('expiration_date');
                    $maxExpiredDate = $items->max('expiration_date');
                    
                    return [
                        'course_id' => $courseId,
                        'course_name' => $course?->name ?? 'Неизвестный курс',
                        'course_category' => $course?->category?->name,
                        'course_subcategory' => $course?->subcategory,
                        'expired_count' => $items->count(),
                        'employees_count' => $uniqueEmployees->count(),
                        'min_expired_date' => $minExpiredDate?->format('Y-m-d'),
                        'max_expired_date' => $maxExpiredDate?->format('Y-m-d'),
                        'employees' => $uniqueEmployees->map(function($item) {
                            $fullName = trim(implode(' ', array_filter([
                                $item->employee->last_name,
                                $item->employee->first_name,
                                $item->employee->middle_name
                            ])));
                            
                            return [
                                'id' => $item->employee->id,
                                'name' => $fullName ?: $item->employee->full_name,
                                'personnel_number' => $item->employee->personnel_number,
                                'position' => $item->employee->position?->name,
                                'department' => $item->employee->department?->name,
                                'brigade' => $item->employee->brigade?->name,
                                'expired_date' => $item->expiration_date?->format('Y-m-d'),
                                'days_overdue' => abs(now()->diffInDays($item->expiration_date, false))
                            ];
                        })->values()
                    ];
                })
                ->values()
                ->sortByDesc('expired_count')
                ->values();
            
            $totalExpired = $expiredByCourse->sum('expired_count');
            $totalEmployees = $expiredByCourse->sum('employees_count');
            
            // Формируем карточки для отображения
            $cards = $expiredByCourse->map(function($item) {
                $level = 'danger';
                if ($item['expired_count'] <= 2) {
                    $level = 'warning';
                } elseif ($item['expired_count'] <= 5) {
                    $level = 'danger';
                } else {
                    $level = 'critical';
                }
                
                return [
                    'title' => $item['course_name'],
                    'count' => $item['expired_count'],
                    'employees_count' => $item['employees_count'],
                    'type' => 'expired',
                    'level' => $level,
                    'color' => $this->getExpiredColor($level),
                    'course_id' => $item['course_id']
                ];
            });
            
            // Дополнительная статистика
            $topEmployees = $expiredRecords
                ->groupBy('employee_id')
                ->map(function($items, $employeeId) {
                    $employee = $items->first()->employee;
                    $fullName = trim(implode(' ', array_filter([
                        $employee->last_name,
                        $employee->first_name,
                        $employee->middle_name
                    ])));
                    
                    return [
                        'id' => $employeeId,
                        'name' => $fullName ?: $employee->full_name,
                        'personnel_number' => $employee->personnel_number,
                        'position' => $employee->position?->name,
                        'department' => $employee->department?->name,
                        'expired_count' => $items->count(),
                        'courses' => $items->map(function($item) {
                            return [
                                'course_id' => $item->course_id,
                                'course_name' => $item->course?->name,
                                'expired_date' => $item->expiration_date?->format('Y-m-d'),
                                'days_overdue' => abs(now()->diffInDays($item->expiration_date, false))
                            ];
                        })->values()
                    ];
                })
                ->sortByDesc('expired_count')
                ->take(10)
                ->values();
            
            return response()->json([
                'period' => $period ?: 'all',
                'period_label' => $this->getPeriodLabel($period),
                'total_expired' => $totalExpired,
                'total_employees_affected' => $totalEmployees,
                'by_course' => $expiredByCourse,
                'cards' => $cards,
                'top_employees' => $topEmployees,
                'filters' => [
                    'available_periods' => ['all', 'month', 'two_months']
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch expired by course',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /trainings/dashboard/expiring-by-period - Истекающие обучения по периодам (объединенный метод)
     */
    public function getExpiringByPeriod(Request $request)
    {
        try {
            $period = $request->get('period', 'month'); // month, two_months
            $today = now();
            
            if ($period === 'month') {
                $startDate = $today;
                $endDate = $today->copy()->addDays(30);
                $periodLabel = 'Ближайший месяц';
                $periodKey = '30_days';
            } elseif ($period === 'two_months') {
                $startDate = $today->copy()->addDays(31);
                $endDate = $today->copy()->addDays(60);
                $periodLabel = 'Через 1-2 месяца';
                $periodKey = '31_60_days';
            } else {
                $startDate = $today;
                $endDate = $today->copy()->addDays(30);
                $periodLabel = 'Ближайший месяц';
                $periodKey = '30_days';
            }
            
            $query = EmployeeCourse::with(['course', 'employee', 'employee.position', 'employee.department', 'employee.brigade'])
                ->where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$startDate, $endDate])
                ->orderBy('expiration_date', 'asc');
            
            // Фильтры
            if ($request->has('brigade_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            if ($request->has('department_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
            
            if ($request->has('position_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('position_id', $request->position_id);
                });
            }
            
            $expiringRecords = $query->get();
            
            // Группируем по курсам
            $expiringByCourse = $expiringRecords
                ->groupBy('course_id')
                ->map(function($items, $courseId) use ($periodKey) {
                    $course = $items->first()->course;
                    $uniqueEmployees = $items->unique('employee_id');
                    $minDaysLeft = $items->min(function($item) {
                        return now()->diffInDays($item->expiration_date, false);
                    });
                    $maxDaysLeft = $items->max(function($item) {
                        return now()->diffInDays($item->expiration_date, false);
                    });
                    
                    // Группируем по срочности
                    $urgentCount = $items->filter(function($item) {
                        return now()->diffInDays($item->expiration_date, false) <= 14;
                    })->count();
                    
                    return [
                        'course_id' => $courseId,
                        'course_name' => $course?->name ?? 'Неизвестный курс',
                        'course_category' => $course?->category?->name,
                        'expiring_count' => $items->count(),
                        'employees_count' => $uniqueEmployees->count(),
                        'urgent_count' => $urgentCount,
                        'min_days_left' => max(0, (int)$minDaysLeft),
                        'max_days_left' => max(0, (int)$maxDaysLeft),
                        'employees' => $uniqueEmployees->map(function($item) use ($periodKey) {
                            $fullName = trim(implode(' ', array_filter([
                                $item->employee->last_name,
                                $item->employee->first_name,
                                $item->employee->middle_name
                            ])));
                            $daysLeft = now()->diffInDays($item->expiration_date, false);
                            
                            return [
                                'id' => $item->employee->id,
                                'name' => $fullName ?: $item->employee->full_name,
                                'personnel_number' => $item->employee->personnel_number,
                                'position' => $item->employee->position?->name,
                                'department' => $item->employee->department?->name,
                                'brigade' => $item->employee->brigade?->name,
                                'expires_date' => $item->expiration_date?->format('Y-m-d'),
                                'days_left' => max(0, $daysLeft),
                                'status_level' => $this->getExpiryStatus($daysLeft)
                            ];
                        })->values()
                    ];
                })
                ->values()
                ->sortBy('min_days_left')
                ->values();
            
            $totalExpiring = $expiringByCourse->sum('expiring_count');
            $totalEmployees = $expiringByCourse->sum('employees_count');
            $totalUrgent = $expiringByCourse->sum('urgent_count');
            
            // Формируем карточки
            $cards = $expiringByCourse->map(function($item) {
                $level = 'normal';
                if ($item['min_days_left'] <= 7) {
                    $level = 'critical';
                } elseif ($item['min_days_left'] <= 14) {
                    $level = 'urgent';
                } elseif ($item['min_days_left'] <= 30) {
                    $level = 'warning';
                }
                
                return [
                    'title' => $item['course_name'],
                    'count' => $item['expiring_count'],
                    'employees_count' => $item['employees_count'],
                    'type' => 'expiring',
                    'level' => $level,
                    'days_left' => $item['min_days_left'],
                    'color' => $this->getExpiringColor($level),
                    'course_id' => $item['course_id']
                ];
            });
            
            return response()->json([
                'period' => $periodKey,
                'period_label' => $periodLabel,
                'date_range' => [
                    'from' => $startDate->format('Y-m-d'),
                    'to' => $endDate->format('Y-m-d')
                ],
                'total_expiring' => $totalExpiring,
                'total_employees_affected' => $totalEmployees,
                'total_urgent' => $totalUrgent,
                'by_course' => $expiringByCourse,
                'cards' => $cards
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch expiring by period',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить цвет для уровня просрочки
     */
    private function getExpiredColor($level)
    {
        $colors = [
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'critical' => '#dc2626'
        ];
        return $colors[$level] ?? '#ef4444';
    }

    /**
     * Получить цвет для уровня истечения
     */
    private function getExpiringColor($level)
    {
        $colors = [
            'normal' => '#10b981',
            'warning' => '#f59e0b',
            'urgent' => '#f97316',
            'critical' => '#ef4444'
        ];
        return $colors[$level] ?? '#f59e0b';
    }

    /**
     * Получить название периода
     */
    private function getPeriodLabel($period)
    {
        $labels = [
            'month' => 'За текущий месяц',
            'two_months' => 'За два месяца',
            'all' => 'За все время'
        ];
        return $labels[$period] ?? 'За все время';
    }
    /**
     * GET /trainings/dashboard/expiring-in-month - Истекающие в ближайший месяц (для карточки 📅)
     */
    public function getExpiringInMonth(Request $request)
    {
        try {
            $today = now();
            $endDate = now()->addDays(30);
            
            $query = EmployeeCourse::with(['course', 'employee'])
                ->where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$today, $endDate])
                ->orderBy('expiration_date', 'asc');
            
            // Фильтр по бригаде
            if ($request->has('brigade_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            // Фильтр по подразделению
            if ($request->has('department_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
            
            $expiringTrainings = $query->get();
            
            // Группируем по курсам
            $expiringByCourse = $expiringTrainings
                ->groupBy('course_id')
                ->map(function($items, $courseId) {
                    $course = $items->first()->course;
                    $uniqueEmployees = $items->unique('employee_id');
                    $minDaysLeft = $items->min(function($item) {
                        return now()->diffInDays($item->expiration_date, false);
                    });
                    
                    return [
                        'course_id' => $courseId,
                        'course_name' => $course?->name ?? 'Неизвестный курс',
                        'expiring_count' => $items->count(),
                        'employees_count' => $uniqueEmployees->count(),
                        'min_days_left' => max(0, (int)$minDaysLeft),
                        'employees' => $uniqueEmployees->map(function($item) {
                            return [
                                'id' => $item->employee->id,
                                'name' => $item->employee->full_name,
                                'position' => $item->employee->position?->name,
                                'expires_date' => $item->expiration_date?->format('Y-m-d'),
                                'days_left' => max(0, now()->diffInDays($item->expiration_date, false))
                            ];
                        })->values()
                    ];
                })
                ->values()
                ->sortBy('min_days_left')
                ->values();
            
            $totalExpiring = $expiringByCourse->sum('expiring_count');
            $totalEmployees = $expiringByCourse->sum('employees_count');
            
            return response()->json([
                'total_expiring' => $totalExpiring,
                'total_employees_affected' => $totalEmployees,
                'period' => '30_days',
                'by_course' => $expiringByCourse,
                'cards' => $expiringByCourse->map(function($item) {
                    return [
                        'title' => $item['course_name'],
                        'count' => $item['expiring_count'],
                        'employees_count' => $item['employees_count'],
                        'type' => 'expiring',
                        'days_left' => $item['min_days_left'],
                        'color' => 'orange'
                    ];
                })
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch expiring in month',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /trainings/dashboard/expiring-in-two-months - Истекающие через 1-2 месяца (для карточки 📅)
     */
    public function getExpiringInTwoMonths(Request $request)
    {
        try {
            $startDate = now()->addDays(31);
            $endDate = now()->addDays(60);
            
            $query = EmployeeCourse::with(['course', 'employee'])
                ->where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$startDate, $endDate])
                ->orderBy('expiration_date', 'asc');
            
            // Фильтр по бригаде
            if ($request->has('brigade_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            // Фильтр по подразделению
            if ($request->has('department_id')) {
                $query->whereHas('employee', function($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
            
            $expiringTrainings = $query->get();
            
            // Группируем по курсам
            $expiringByCourse = $expiringTrainings
                ->groupBy('course_id')
                ->map(function($items, $courseId) {
                    $course = $items->first()->course;
                    $uniqueEmployees = $items->unique('employee_id');
                    $minDaysLeft = $items->min(function($item) {
                        return now()->diffInDays($item->expiration_date, false);
                    });
                    
                    return [
                        'course_id' => $courseId,
                        'course_name' => $course?->name ?? 'Неизвестный курс',
                        'expiring_count' => $items->count(),
                        'employees_count' => $uniqueEmployees->count(),
                        'min_days_left' => max(0, (int)$minDaysLeft),
                        'employees' => $uniqueEmployees->map(function($item) {
                            return [
                                'id' => $item->employee->id,
                                'name' => $item->employee->full_name,
                                'position' => $item->employee->position?->name,
                                'expires_date' => $item->expiration_date?->format('Y-m-d'),
                                'days_left' => max(0, now()->diffInDays($item->expiration_date, false))
                            ];
                        })->values()
                    ];
                })
                ->values()
                ->sortBy('min_days_left')
                ->values();
            
            $totalExpiring = $expiringByCourse->sum('expiring_count');
            $totalEmployees = $expiringByCourse->sum('employees_count');
            
            return response()->json([
                'total_expiring' => $totalExpiring,
                'total_employees_affected' => $totalEmployees,
                'period' => '31_60_days',
                'by_course' => $expiringByCourse,
                'cards' => $expiringByCourse->map(function($item) {
                    return [
                        'title' => $item['course_name'],
                        'count' => $item['expiring_count'],
                        'employees_count' => $item['employees_count'],
                        'type' => 'expiring_soon',
                        'days_left' => $item['min_days_left'],
                        'color' => 'yellow'
                    ];
                })
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch expiring in two months',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /trainings/dashboard/summary - Сводка для карточек дашборда
     */
    public function getDashboardSummary(Request $request)
    {
        try {
            $today = now();
            $monthEnd = now()->addDays(30);
            $twoMonthsEnd = now()->addDays(60);
            
            // Просроченные
            $expiredQuery = EmployeeCourse::where('status', 'expired')
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<', $today);
            
            if ($request->has('brigade_id')) {
                $expiredQuery->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            $expiredTotal = $expiredQuery->count();
            $expiredEmployees = $expiredQuery->distinct('employee_id')->count('employee_id');
            
            // Истекающие в ближайший месяц
            $expiringMonthQuery = EmployeeCourse::where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$today, $monthEnd]);
            
            if ($request->has('brigade_id')) {
                $expiringMonthQuery->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            $expiringMonthTotal = $expiringMonthQuery->count();
            $expiringMonthEmployees = $expiringMonthQuery->distinct('employee_id')->count('employee_id');
            
            // Истекающие через 1-2 месяца
            $expiringTwoMonthsQuery = EmployeeCourse::where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$monthEnd->addDay(), $twoMonthsEnd]);
            
            if ($request->has('brigade_id')) {
                $expiringTwoMonthsQuery->whereHas('employee', function($q) use ($request) {
                    $q->where('brigade_id', $request->brigade_id);
                });
            }
            
            $expiringTwoMonthsTotal = $expiringTwoMonthsQuery->count();
            $expiringTwoMonthsEmployees = $expiringTwoMonthsQuery->distinct('employee_id')->count('employee_id');
            
            return response()->json([
                'expired' => [
                    'total' => $expiredTotal,
                    'employees_affected' => $expiredEmployees,
                    'color' => 'red',
                    'icon' => '🔴'
                ],
                'expiring_month' => [
                    'total' => $expiringMonthTotal,
                    'employees_affected' => $expiringMonthEmployees,
                    'period' => 'Ближайший месяц',
                    'color' => 'orange',
                    'icon' => '📅'
                ],
                'expiring_two_months' => [
                    'total' => $expiringTwoMonthsTotal,
                    'employees_affected' => $expiringTwoMonthsEmployees,
                    'period' => 'Через 1-2 месяца',
                    'color' => 'yellow',
                    'icon' => '📅'
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch dashboard summary',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /trainings/assign-to-position - Назначить курс всем сотрудникам должности
     */
    public function assignToPosition(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'position_id' => 'required|exists:positions,id',
                'course_id' => 'required|exists:courses,id',
                'assigned_date' => 'nullable|date',
                'expiration_date' => 'nullable|date|after:assigned_date',
                'force' => 'nullable|boolean' // Принудительное обновление существующих
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Получаем должность
            $position = Position::findOrFail($request->position_id);
            $course = Course::findOrFail($request->course_id);
            
            // Получаем всех сотрудников этой должности
            $employees = Employee::where('position_id', $request->position_id)
                ->where('status', 'active')
                ->get();
            
            if ($employees->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет активных сотрудников с этой должностью'
                ], 400);
            }
            
            // Обработка дат
            $assignedDate = $request->has('assigned_date') 
                ? \Carbon\Carbon::parse($request->assigned_date) 
                : now();
            
            $expirationDate = null;
            if ($request->has('expiration_date')) {
                $expirationDate = \Carbon\Carbon::parse($request->expiration_date);
            } elseif ($course->periodicity_months) {
                $expirationDate = $assignedDate->copy()->addMonths($course->periodicity_months);
            }
            
            $force = $request->get('force', false);
            $assigned = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($employees as $employee) {
                try {
                    // Проверяем существующее назначение
                    $existing = EmployeeCourse::where('employee_id', $employee->id)
                        ->where('course_id', $request->course_id)
                        ->first();
                    
                    if ($existing) {
                        if ($force) {
                            // Обновляем существующее назначение
                            $existing->assigned_date = $assignedDate;
                            $existing->expiration_date = $expirationDate;
                            $existing->status = 'active';
                            $existing->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }
                    
                    // Создаем новое назначение
                    EmployeeCourse::create([
                        'employee_id' => $employee->id,
                        'course_id' => $request->course_id,
                        'status' => 'required',
                        'assigned_date' => $assignedDate,
                        'expiration_date' => $expirationDate,
                        'last_reminder_sent' => null
                    ]);
                    $assigned++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
            // Создаем требование в матрице компетенций
            $requirement = PositionCourseRequirement::updateOrCreate(
                [
                    'position_id' => $request->position_id,
                    'course_id' => $request->course_id
                ],
                [
                    'is_required' => true
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => "Курс '{$course->name}' назначен должности '{$position->name}'",
                'statistics' => [
                    'position_id' => $position->id,
                    'position_name' => $position->name,
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'total_employees' => $employees->count(),
                    'assigned' => $assigned,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => $errors
                ],
                'requirement_created' => true
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Assign to position error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign course to position',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /trainings/assign-to-brigade - Назначить курс всем сотрудникам бригады
     */
    public function assignToBrigade(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'brigade_id' => 'required|exists:brigades,id',
                'course_id' => 'required|exists:courses,id',
                'assigned_date' => 'nullable|date',
                'expiration_date' => 'nullable|date|after:assigned_date',
                'force' => 'nullable|boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            $brigade = Brigade::findOrFail($request->brigade_id);
            $course = Course::findOrFail($request->course_id);
            
            // Получаем всех сотрудников бригады
            $employees = Employee::where('brigade_id', $request->brigade_id)
                ->where('status', 'active')
                ->get();
            
            if ($employees->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет активных сотрудников в этой бригаде'
                ], 400);
            }
            
            $assignedDate = $request->has('assigned_date') 
                ? \Carbon\Carbon::parse($request->assigned_date) 
                : now();
            
            $expirationDate = null;
            if ($request->has('expiration_date')) {
                $expirationDate = \Carbon\Carbon::parse($request->expiration_date);
            } elseif ($course->periodicity_months) {
                $expirationDate = $assignedDate->copy()->addMonths($course->periodicity_months);
            }
            
            $force = $request->get('force', false);
            $assigned = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($employees as $employee) {
                try {
                    $existing = EmployeeCourse::where('employee_id', $employee->id)
                        ->where('course_id', $request->course_id)
                        ->first();
                    
                    if ($existing) {
                        if ($force) {
                            $existing->assigned_date = $assignedDate;
                            $existing->expiration_date = $expirationDate;
                            $existing->status = 'active';
                            $existing->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }
                    
                    EmployeeCourse::create([
                        'employee_id' => $employee->id,
                        'course_id' => $request->course_id,
                        'status' => 'required',
                        'assigned_date' => $assignedDate,
                        'expiration_date' => $expirationDate,
                        'last_reminder_sent' => null
                    ]);
                    $assigned++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
            // Создаем требование в матрице для бригады
            $requirement = BrigadeCourseRequirement::updateOrCreate(
                [
                    'brigade_id' => $request->brigade_id,
                    'course_id' => $request->course_id
                ],
                [
                    'is_required' => true
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => "Курс '{$course->name}' назначен бригаде '{$brigade->name}'",
                'statistics' => [
                    'brigade_id' => $brigade->id,
                    'brigade_name' => $brigade->name,
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'total_employees' => $employees->count(),
                    'assigned' => $assigned,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => $errors
                ],
                'requirement_created' => true
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Assign to brigade error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign course to brigade',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /trainings/assign-to-department - Назначить курс всем сотрудникам подразделения
     */
    public function assignToDepartment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'department_id' => 'required|exists:departments,id',
                'course_id' => 'required|exists:courses,id',
                'include_children' => 'nullable|boolean',
                'assigned_date' => 'nullable|date',
                'expiration_date' => 'nullable|date|after:assigned_date',
                'force' => 'nullable|boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            $department = \App\Models\Department::findOrFail($request->department_id);
            $course = Course::findOrFail($request->course_id);
            
            // Получаем сотрудников подразделения
            $employeeIds = \App\Models\Employee::where('department_id', $request->department_id)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
            
            // Включаем дочерние подразделения
            $includeChildren = $request->get('include_children', false);
            if ($includeChildren) {
                $childIds = \App\Models\Department::where('parent_id', $request->department_id)
                    ->pluck('id')
                    ->toArray();
                
                $childEmployeeIds = \App\Models\Employee::whereIn('department_id', $childIds)
                    ->where('status', 'active')
                    ->pluck('id')
                    ->toArray();
                
                $employeeIds = array_merge($employeeIds, $childEmployeeIds);
            }
            
            $employees = Employee::whereIn('id', $employeeIds)->get();
            
            if ($employees->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет активных сотрудников в этом подразделении'
                ], 400);
            }
            
            $assignedDate = $request->has('assigned_date') 
                ? \Carbon\Carbon::parse($request->assigned_date) 
                : now();
            
            $expirationDate = null;
            if ($request->has('expiration_date')) {
                $expirationDate = \Carbon\Carbon::parse($request->expiration_date);
            } elseif ($course->periodicity_months) {
                $expirationDate = $assignedDate->copy()->addMonths($course->periodicity_months);
            }
            
            $force = $request->get('force', false);
            $assigned = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($employees as $employee) {
                try {
                    $existing = EmployeeCourse::where('employee_id', $employee->id)
                        ->where('course_id', $request->course_id)
                        ->first();
                    
                    if ($existing) {
                        if ($force) {
                            $existing->assigned_date = $assignedDate;
                            $existing->expiration_date = $expirationDate;
                            $existing->status = 'active';
                            $existing->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }
                    
                    EmployeeCourse::create([
                        'employee_id' => $employee->id,
                        'course_id' => $request->course_id,
                        'status' => 'required',
                        'assigned_date' => $assignedDate,
                        'expiration_date' => $expirationDate,
                        'last_reminder_sent' => null
                    ]);
                    $assigned++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Курс '{$course->name}' назначен подразделению '{$department->name}'",
                'statistics' => [
                    'department_id' => $department->id,
                    'department_name' => $department->name,
                    'include_children' => $includeChildren,
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'total_employees' => $employees->count(),
                    'assigned' => $assigned,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => $errors
                ]
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Assign to department error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign course to department',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить историю обучения
     */
    private function getTrainingHistory($training)
    {
        $history = [];
        
        // Назначение
        if ($training->assigned_date) {
            $history[] = [
                'action' => 'assigned',
                'date' => $training->assigned_date->format('Y-m-d H:i:s'),
                'user' => 'Система'
            ];
        }
        
        // Завершение
        if ($training->completed_date) {
            $history[] = [
                'action' => 'completed',
                'date' => $training->completed_date->format('Y-m-d H:i:s'),
                'user' => 'Система'
            ];
        }
        
        // Продления (можно добавить из отдельной таблицы истории)
        
        // Отметки об отправке напоминаний
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
}
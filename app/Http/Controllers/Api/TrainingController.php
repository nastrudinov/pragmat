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
            
            // История изменений (имитация, можно расширить с таблицей истории)
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
                        'certificateUrl' => $training->certificate_file_path
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
            $newExpirationDate = now()->addMonths($periodicityMonths);
            
            $training->status = 'active';
            $training->completed_date = $request->get('completed_date', now());
            $training->expiration_date = $newExpirationDate;
            $training->certificate_file_path = $certificatePath;
            $training->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Обучение отмечено как пройденное',
                'completedDate' => $training->completed_date->format('Y-m-d'),
                'newExpiresDate' => $newExpirationDate->format('Y-m-d')
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Training not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
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
            
            // Рассчитываем новую дату истечения
            if ($request->has('new_expiration_date')) {
                $newExpirationDate = $request->new_expiration_date;
            } else {
                $months = $request->get('months', 12);
                $newExpirationDate = now()->addMonths($months);
            }
            
            $oldExpirationDate = $training->expiration_date;
            $training->expiration_date = $newExpirationDate;
            $training->status = 'active';
            $training->save();
            
            $daysLeft = now()->diffInDays($newExpirationDate, false);
            
            return response()->json([
                'success' => true,
                'message' => 'Срок продлен',
                'newExpiresDate' => $newExpirationDate->format('Y-m-d'),
                'daysLeft' => max(0, $daysLeft),
                'oldExpiresDate' => $oldExpirationDate?->format('Y-m-d')
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Training not found'
            ], 404);
        } catch (\Exception $e) {
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
            
            // Рассчитываем дату истечения
            $assignedDate = $request->get('assigned_date', now());
            $expirationDate = $request->get('expiration_date');
            
            if (!$expirationDate && $course && $course->periodicity_months) {
                $expirationDate = now()->addMonths($course->periodicity_months);
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
            
            return response()->json([
                'success' => true,
                'message' => 'Обучение назначено',
                'trainingId' => $training->id,
                'employeeId' => $training->employee_id,
                'expiresDate' => $expirationDate?->format('Y-m-d'),
                'needsAttention' => $needsAttention
            ], 201);
            
        } catch (\Exception $e) {
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
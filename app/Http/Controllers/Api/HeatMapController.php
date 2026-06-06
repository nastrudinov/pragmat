<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Course;
use App\Models\Brigade;
use App\Models\Position;
use App\Models\EmployeeCourse;
use App\Models\Department;  
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HeatMapController extends Controller
{
    /**
     * Ключ для кэша тепловой карты
     */
    private function getCacheKey($prefix, $request)
    {
        $params = [
            'brigade_id' => $request->get('brigade_id'),
            'department_id' => $request->get('department_id'),
            'position_id' => $request->get('position_id'),
            'status' => $request->get('status'),
            'search' => $request->get('search')
        ];
        
        return "heatmap_{$prefix}_" . md5(json_encode($params));
    }
    
    /**
     * 3.1 GET /heatmap - Данные для тепловой карты
     */
    public function getHeatmapData(Request $request)
    {
        try {
            $cacheKey = $this->getCacheKey('data', $request);
            $cacheTTL = now()->addMinutes(15);
            
            $result = Cache::remember($cacheKey, $cacheTTL, function() use ($request) {
                // Получаем все курсы
                $courses = $this->getCachedCourses();
                
                $trainingTypes = $courses->map(function($course) {
                    return [
                        'id' => 'course_' . $course->id,
                        'name' => $course->name,
                        'shortName' => $this->getSafeShortName($course->name),
                        'category' => $course->category?->name ?? 'Без категории',
                        'categoryId' => $course->category_id
                    ];
                });
                
                // Строим сырой SQL запрос (УБРАЛИ e.deleted_at)
                $employeeQuery = "
                    SELECT 
                        e.id,
                        e.full_name,
                        e.position_id,
                        e.brigade_id,
                        e.department_id,
                        e.status,
                        p.name as position_name,
                        b.name as brigade_name,
                        d.name as department_name
                    FROM employees e
                    LEFT JOIN positions p ON p.id = e.position_id
                    LEFT JOIN brigades b ON b.id = e.brigade_id
                    LEFT JOIN departments d ON d.id = e.department_id
                    WHERE 1=1
                ";
                
                $bindings = [];
                
                // Применяем фильтры
                if ($request->filled('brigade_id')) {
                    $employeeQuery .= " AND e.brigade_id = ?";
                    $bindings[] = $request->brigade_id;
                }
                
                if ($request->filled('department_id')) {
                    $employeeQuery .= " AND e.department_id = ?";
                    $bindings[] = $request->department_id;
                }
                
                if ($request->filled('position_id')) {
                    $employeeQuery .= " AND e.position_id = ?";
                    $bindings[] = $request->position_id;
                }
                
                if ($request->filled('status')) {
                    $employeeQuery .= " AND e.status = ?";
                    $bindings[] = $request->status;
                }
                
                if ($request->filled('search')) {
                    $employeeQuery .= " AND e.full_name LIKE ?";
                    $bindings[] = "%{$request->search}%";
                }
                
                $employees = DB::select($employeeQuery, $bindings);
                
                // Получаем все обучения сотрудников одним запросом
                $employeeCoursesMap = [];
                
                if (!empty($employees)) {
                    $employeeIds = array_column($employees, 'id');
                    $employeeIdsStr = implode(',', $employeeIds);
                    
                    $trainingsQuery = "
                        SELECT 
                            ec.employee_id,
                            ec.course_id,
                            ec.status
                        FROM employee_courses ec
                        WHERE ec.employee_id IN ({$employeeIdsStr})
                    ";
                    
                    $trainings = DB::select($trainingsQuery);
                    
                    foreach ($trainings as $training) {
                        $employeeCoursesMap[$training->employee_id][$training->course_id] = $training->status;
                    }
                }
                
                // Формируем ответ
                $formattedEmployees = [];
                foreach ($employees as $employee) {
                    $trainingsStatus = [];
                    $overallStatus = 'active';
                    
                    foreach ($courses as $course) {
                        $status = $employeeCoursesMap[$employee->id][$course->id] ?? null;
                        
                        // Пропускаем курсы, которые не назначены сотруднику (noData)
                        if (!$status) {
                            continue;
                        }
                        
                        $trainingsStatus['course_' . $course->id] = $status;
                        
                        if ($status === 'expired') {
                            $overallStatus = 'expired';
                        } elseif ($status === 'expiring' && $overallStatus !== 'expired') {
                            $overallStatus = 'expiring';
                        } elseif ($status === 'required' && $overallStatus !== 'expired' && $overallStatus !== 'expiring') {
                            $overallStatus = 'required';
                        }
                    }
                    
                    $trainingsStatus['_overall'] = $overallStatus;
                    
                    $formattedEmployees[] = [
                        'id' => $employee->id,
                        'name' => $this->sanitizeString($employee->full_name),
                        'position' => $this->sanitizeString($employee->position_name ?? 'Не указана'),
                        'positionId' => $employee->position_id,
                        'brigade' => $this->sanitizeString($employee->brigade_name ?? 'Не указана'),
                        'brigadeId' => $employee->brigade_id,
                        'department' => $this->sanitizeString($employee->department_name ?? 'Не указано'),
                        'departmentId' => $employee->department_id,
                        'status' => $employee->status,
                        'trainings' => $trainingsStatus,
                        'overallStatus' => $overallStatus
                    ];
                }
                
                // Получаем справочники для фильтров
                $brigades = Cache::remember('heatmap_brigades', now()->addHours(24), function() {
                    return DB::table('brigades')->orderBy('name')->pluck('name')->toArray();
                });
                
                $departments = Cache::remember('heatmap_departments', now()->addHours(24), function() {
                    return DB::table('departments')->orderBy('name')->pluck('name')->toArray();
                });
                
                return [
                    'employees' => $formattedEmployees,
                    'trainingTypes' => $trainingTypes,
                    'brigades' => $brigades,
                    'departments' => $departments,
                    'totalEmployees' => count($employees),
                    'totalTrainings' => $courses->count()
                ];
            });
            
            return response()->json($result)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            \Log::error('Heatmap error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch heatmap data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
            
        /**
         * 3.2 GET /heatmap/employee/{employeeId} - Данные по сотруднику
         */
   public function getEmployeeData($employeeId)
{
    try {
        $employee = Employee::with([
            'position:id,name',
            'brigade:id,name',
            'employeeCourses' => function($query) {
                $query->select('id', 'employee_id', 'course_id', 'status', 'expiration_date', 'assigned_date', 'completed_date', 'certificate_file_path')
                    ->with('course:id,name');
            }
        ])->find($employeeId);
        
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }
        
        $trainings = [];
        
        foreach ($employee->employeeCourses as $employeeCourse) {
            $trainings[] = [
                'id' => 'course_' . $employeeCourse->course_id,
                'name' => $this->sanitizeString($employeeCourse->course?->name ?? 'Неизвестный курс'),
                'status' => $employeeCourse->status,
                'expiresDate' => $employeeCourse->expiration_date?->format('Y-m-d'),
                'assignedDate' => $employeeCourse->assigned_date?->format('Y-m-d'),
                'completedDate' => $employeeCourse->completed_date?->format('Y-m-d'),
                'daysLeft' => $employeeCourse->expiration_date 
                    ? now()->diffInDays($employeeCourse->expiration_date, false) 
                    : null,
                'certificateUrl' => $employeeCourse->certificate_file_path
            ];
        }
        
        // Сортировка
        $statusOrder = ['expired' => 0, 'expiring' => 1, 'required' => 2,'active' => 3];
        usort($trainings, function($a, $b) use ($statusOrder) {
            $orderA = $statusOrder[$a['status']] ?? 4;
            $orderB = $statusOrder[$b['status']] ?? 4;
            return $orderA - $orderB;
        });
        
        $stats = [
            'total' => count($trainings),
            'active' => count(array_filter($trainings, fn($t) => $t['status'] === 'active')),
            'expiring' => count(array_filter($trainings, fn($t) => $t['status'] === 'expiring')),
            'expired' => count(array_filter($trainings, fn($t) => $t['status'] === 'expired')),
            'required' => count(array_filter($trainings, fn($t) => $t['status'] === 'required')),
            'noData' => 0
        ];
        
        $totalWithExpiration = $stats['active'] + $stats['expiring'] + $stats['expired'];
        $compliancePercentage = $totalWithExpiration > 0 
            ? round((($stats['active'] + $stats['expiring']) / $totalWithExpiration) * 100)
            : 0;
        
        return response()->json([
            'employeeId' => $employee->id,
            'name' => $this->sanitizeString($employee->full_name),
            'position' => $this->sanitizeString($employee->position?->name ?? 'Не указана'),
            'positionId' => $employee->position_id,
            'brigade' => $this->sanitizeString($employee->brigade?->name ?? 'Не указана'),
            'brigadeId' => $employee->brigade_id,
            'status' => $employee->status,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'trainings' => $trainings,
            'statistics' => $stats,
            'compliancePercentage' => $compliancePercentage
        ])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to fetch employee data',
            'message' => $e->getMessage()
        ], 500);
    }
}
    
    /**
     * 3.3 GET /heatmap/summary - Сводная статистика
     */
    public function getSummary(Request $request)
    {
        try {
            $cacheKey = 'heatmap_summary_' . ($request->get('brigade_id') ?? 'all');
            $cacheTTL = now()->addMinutes(15);
            
            $result = Cache::remember($cacheKey, $cacheTTL, function() {
                // Используем агрегатные запросы вместо загрузки всех данных
                $totalEmployees = Employee::count();
                $totalTrainings = EmployeeCourse::count();
                
                // Одним запросом получаем статистику по статусам
                $statusCountsRaw = EmployeeCourse::select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
                
                $statusCounts = [
                    'active' => $statusCountsRaw['active'] ?? 0,
                    'expiring' => $statusCountsRaw['expiring'] ?? 0,
                    'expired' => $statusCountsRaw['expired'] ?? 0,
                    'required' => $statusCountsRaw['required'] ?? 0,
                    'noData' => Employee::doesntHave('employeeCourses')->count()
                ];
                
                // Статистика по бригадам - оптимизированный запрос
                $brigades = Brigade::withCount(['employees'])->get();
                $employeeIdsByBrigade = [];
                
                foreach ($brigades as $brigade) {
                    $employeeIds = $brigade->employees->pluck('id')->toArray();
                    $employeeIdsByBrigade[$brigade->id] = $employeeIds;
                }
                
                $byBrigade = [];
                foreach ($brigades as $brigade) {
                    $employeeIds = $employeeIdsByBrigade[$brigade->id] ?? [];
                    
                    if (empty($employeeIds)) {
                        $byBrigade[$this->sanitizeString($brigade->name)] = [
                            'total' => 0,
                            'totalTrainings' => 0,
                            'active' => 0,
                            'expiring' => 0,
                            'expired' => 0,
                            'noData' => 0
                        ];
                        continue;
                    }
                    
                    // Один запрос для получения статистики по бригаде
                    $stats = EmployeeCourse::whereIn('employee_id', $employeeIds)
                        ->select('status', DB::raw('count(*) as count'))
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray();
                    
                    $employeesCount = count($employeeIds);
                    $noDataEmployees = Employee::whereIn('id', $employeeIds)
                        ->whereDoesntHave('employeeCourses')
                        ->count();
                    
                    $byBrigade[$this->sanitizeString($brigade->name)] = [
                        'total' => $employeesCount,
                        'totalTrainings' => array_sum($stats),
                        'active' => $stats['active'] ?? 0,
                        'expiring' => $stats['expiring'] ?? 0,
                        'expired' => $stats['expired'] ?? 0,
                        'noData' => $noDataEmployees
                    ];
                }
                
                // Статистика по должностям
                $positions = Position::withCount(['employees'])->get();
                $byPosition = [];
                
                foreach ($positions as $position) {
                    $employeeIds = $position->employees->pluck('id')->toArray();
                    
                    if (empty($employeeIds)) {
                        continue;
                    }
                    
                    $stats = EmployeeCourse::whereIn('employee_id', $employeeIds)
                        ->select('status', DB::raw('count(*) as count'))
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray();
                    
                    $byPosition[$this->sanitizeString($position->name)] = [
                        'total' => count($employeeIds),
                        'active' => $stats['active'] ?? 0,
                        'expiring' => $stats['expiring'] ?? 0,
                        'expired' => $stats['expired'] ?? 0
                    ];
                }
                
                // Процент соответствия - оптимизированный запрос
                $compliantEmployees = Employee::whereDoesntHave('employeeCourses', function($query) {
                    $query->where('status', 'expired');
                })->count();
                
                $compliancePercentage = $totalEmployees > 0 
                    ? round(($compliantEmployees / $totalEmployees) * 100)
                    : 0;
                
                return [
                    'totalEmployees' => $totalEmployees,
                    'totalTrainings' => $totalTrainings,
                    'statusCounts' => $statusCounts,
                    'byBrigade' => $byBrigade,
                    'byPosition' => $byPosition,
                    'compliancePercentage' => $compliancePercentage,
                    'compliantEmployees' => $compliantEmployees,
                    'nonCompliantEmployees' => $totalEmployees - $compliantEmployees
                ];
            });
            
            return response()->json($result)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch summary',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 3.4 GET /heatmap/export - Экспорт тепловой карты
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'csv');
            $brigadeId = $request->get('brigade_id');
            $positionId = $request->get('position_id');
            
            $courses = $this->getCachedCourses();
            
            $query = Employee::with(['position:id,name', 'brigade:id,name', 'employeeCourses:id,employee_id,course_id,status']);
            
            if ($brigadeId) {
                $query->where('brigade_id', $brigadeId);
            }
            
            if ($positionId) {
                $query->where('position_id', $positionId);
            }
            
            $employees = $query->get();
            $employeeCoursesMap = [];
            
            foreach ($employees as $employee) {
                $employeeCoursesMap[$employee->id] = $employee->employeeCourses->keyBy('course_id');
            }
            
            $data = [];
            foreach ($employees as $employee) {
                $row = [
                    'ID' => $employee->id,
                    'ФИО' => $this->sanitizeString($employee->full_name),
                    'Должность' => $this->sanitizeString($employee->position?->name ?? ''),
                    'Бригада' => $this->sanitizeString($employee->brigade?->name ?? ''),
                    'Статус' => $employee->status
                ];
                
                foreach ($courses as $course) {
                    $employeeCourse = $employeeCoursesMap[$employee->id][$course->id] ?? null;
                    $status = $employeeCourse ? $this->getStatusText($employeeCourse->status) : 'Нет данных';
                    $row[$this->sanitizeString($course->name)] = $status;
                }
                
                $data[] = $row;
            }
            
            if ($format === 'csv') {
                $fileName = 'heatmap_export_' . date('Y-m-d_His') . '.csv';
                $headers = [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                ];
                
                $callback = function() use ($data) {
                    $file = fopen('php://output', 'w');
                    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                    
                    if (!empty($data)) {
                        fputcsv($file, array_keys($data[0]));
                    }
                    
                    foreach ($data as $row) {
                        fputcsv($file, $row);
                    }
                    
                    fclose($file);
                };
                
                return response()->stream($callback, 200, $headers);
            }
            
            return response()->json([
                'data' => $data,
                'total' => count($data)
            ])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to export heatmap',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 3.5 GET /heatmap/filters - Доступные фильтры
     */
    public function getFilters()
    {
        try {
            $cacheKey = 'heatmap_filters';
            $cacheTTL = now()->addHours(24);
            
            $result = Cache::remember($cacheKey, $cacheTTL, function() {
                $brigades = Brigade::orderBy('name')
                    ->get(['id', 'name'])
                    ->map(function($brigade) {
                        return [
                            'id' => $brigade->id,
                            'name' => $this->sanitizeString($brigade->name),
                            'employeeCount' => $brigade->employees()->count()
                        ];
                    });
                
                $statuses = [
                    ['id' => 'active', 'name' => 'Активные', 'color' => '#10b981'],
                    ['id' => 'expiring', 'name' => 'Истекают', 'color' => '#f59e0b'],
                    ['id' => 'expired', 'name' => 'Просрочены', 'color' => '#ef4444'],
                    ['id' => 'required', 'name' => 'Требуются', 'color' => '#8b5cf6'],
                    ['id' => 'noData', 'name' => 'Нет данных', 'color' => '#9ca3af']
                ];
                
                $positions = Position::orderBy('name')
                    ->get(['id', 'name'])
                    ->map(function($position) {
                        return [
                            'id' => $position->id,
                            'name' => $this->sanitizeString($position->name),
                            'employeeCount' => $position->employees()->count()
                        ];
                    });
                
                $categories = DB::table('course_categories')
                    ->leftJoin('courses', 'course_categories.id', '=', 'courses.category_id')
                    ->select(
                        'course_categories.id',
                        'course_categories.name',
                        DB::raw('COUNT(DISTINCT courses.id) as course_count')
                    )
                    ->groupBy('course_categories.id', 'course_categories.name')
                    ->get()
                    ->map(function($category) {
                        return [
                            'id' => $category->id,
                            'name' => $this->sanitizeString($category->name),
                            'course_count' => $category->course_count
                        ];
                    });
                
                return [
                    'brigades' => $brigades,
                    'statuses' => $statuses,
                    'positions' => $positions,
                    'categories' => $categories
                ];
            });
            
            return response()->json($result)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch filters',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить матрицу компетенций (расширенная версия)
     */
    public function getCompetenceMatrix(Request $request)
    {
        try {
            $cacheKey = 'heatmap_matrix_' . ($request->get('brigade_id') ?? 'all') . '_' . ($request->get('position_id') ?? 'all');
            $cacheTTL = now()->addMinutes(10);
            
            $result = Cache::remember($cacheKey, $cacheTTL, function() use ($request) {
                $brigadeId = $request->get('brigade_id');
                $positionId = $request->get('position_id');
                
                $courses = $this->getCachedCourses();
                
                $query = Employee::with([
                    'position:id,name',
                    'brigade:id,name',
                    'employeeCourses:id,employee_id,course_id,status'
                ]);
                
                if ($brigadeId) {
                    $query->where('brigade_id', $brigadeId);
                }
                
                if ($positionId) {
                    $query->where('position_id', $positionId);
                }
                
                $employees = $query->get(['id', 'full_name', 'position_id', 'brigade_id']);
                
                // Индексация данных
                $employeeCoursesMap = [];
                foreach ($employees as $employee) {
                    $employeeCoursesMap[$employee->id] = $employee->employeeCourses->keyBy('course_id');
                }
                
                $matrix = [];
                $rowStats = [];
                $allStatusCounts = [];
                
                foreach ($courses as $course) {
                    $allStatusCounts['course_' . $course->id] = [
                        'active' => 0,
                        'expiring' => 0,
                        'expired' => 0,
                        'required' => 0,
                        'noData' => 0
                    ];
                }
                
                foreach ($employees as $employee) {
                    $row = [
                        'employeeId' => $employee->id,
                        'employeeName' => $employee->full_name,
                        'position' => $employee->position?->name,
                        'brigade' => $employee->brigade?->name
                    ];
                    
                    $expiredCount = 0;
                    $activeCount = 0;
                    $noDataCount = 0;
                    
                    foreach ($courses as $course) {
                        $employeeCourse = $employeeCoursesMap[$employee->id][$course->id] ?? null;
                        
                        if (!$employeeCourse) {
                            $status = 'noData';
                            $noDataCount++;
                            $allStatusCounts['course_' . $course->id]['noData']++;
                        } else {
                            $status = $employeeCourse->status;
                            $allStatusCounts['course_' . $course->id][$status]++;
                            
                            if ($status === 'expired') {
                                $expiredCount++;
                            } elseif ($status === 'active' || $status === 'expiring') {
                                $activeCount++;
                            }
                        }
                        
                        $row['course_' . $course->id] = $status;
                    }
                    
                    $totalCourses = $courses->count();
                    $complianceScore = $totalCourses > 0 
                        ? round((($activeCount) / $totalCourses) * 100)
                        : 0;
                    
                    $row['_complianceScore'] = $complianceScore;
                    $row['_expiredCount'] = $expiredCount;
                    $row['_activeCount'] = $activeCount;
                    $row['_noDataCount'] = $noDataCount;
                    
                    $matrix[] = $row;
                    $rowStats[] = $complianceScore;
                }
                
                // Колоночная статистика
                $totalEmployees = $employees->count();
                $columnStats = [];
                
                foreach ($courses as $course) {
                    $stats = $allStatusCounts['course_' . $course->id];
                    $columnStats['course_' . $course->id] = [
                        'name' => $course->name,
                        'category' => $course->category?->name,
                        'stats' => $stats,
                        'complianceRate' => $totalEmployees > 0 
                            ? round((($stats['active'] + $stats['expiring']) / $totalEmployees) * 100)
                            : 0
                    ];
                }
                
                $averageCompliance = count($rowStats) > 0 
                    ? round(array_sum($rowStats) / count($rowStats))
                    : 0;
                
                $formattedCourses = $courses->map(function($course) {
                    return [
                        'id' => 'course_' . $course->id,
                        'name' => $course->name,
                        'category' => $course->category?->name
                    ];
                });
                
                return [
                    'courses' => $formattedCourses,
                    'matrix' => $matrix,
                    'columnStats' => $columnStats,
                    'averageCompliance' => $averageCompliance,
                    'totalEmployees' => $totalEmployees,
                    'totalCourses' => $courses->count()
                ];
            });
            
            return response()->json($result, 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch competence matrix',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получение курсов с кэшированием
     */
    private function getCachedCourses()
    {
        return Cache::remember('all_courses_with_categories', now()->addHours(24), function() {
            return Course::with('category:id,name')
                ->orderBy('category_id')
                ->orderBy('name')
                ->get(['id', 'name', 'category_id']);
        });
    }
    
    /**
     * Безопасное получение короткого имени (без проблем с UTF-8)
     */
    private function getSafeShortName($fullName)
    {
        if (empty($fullName)) {
            return 'N/A';
        }
        
        $shortNames = [
            'Электробезопасность' => 'ЭБ',
            'Охрана труда' => 'ОТ',
            'Пожарная безопасность' => 'ПБ',
            'ГО и ЧС' => 'ГО',
            'Промышленная безопасность' => 'ПрБ',
            'BOSIET' => 'BOS',
            'Медицинский осмотр' => 'МО',
            'Управление проектами' => 'УП',
            'Leadership' => 'LDR',
            'English' => 'ENG'
        ];
        
        foreach ($shortNames as $key => $short) {
            if (strpos($fullName, $key) !== false) {
                return $short;
            }
        }
        
        $words = preg_split('/\s+/', trim($fullName));
        $short = '';
        foreach ($words as $word) {
            if (strlen($word) > 0) {
                $char = mb_substr($word, 0, 1, 'UTF-8');
                $short .= $char;
            }
        }
        
        $short = mb_strtoupper($short, 'UTF-8');
        return strlen($short) > 4 ? mb_substr($short, 0, 4, 'UTF-8') : $short;
    }
    
    /**
     * Санитизация строки (удаление некорректных UTF-8 символов)
     */
    private function sanitizeString($string)
    {
        if (empty($string)) {
            return '';
        }
        
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        $string = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        
        return trim($string);
    }
    
    /**
     * Получить текстовое описание статуса
     */
    private function getStatusText($status)
    {
        $statuses = [
            'active' => 'Активно',
            'expiring' => 'Истекает',
            'expired' => 'Просрочено',
            'required' => 'Требуется',
            'noData' => 'Нет данных'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    /**
     * Очистка кэша тепловой карты
     */
    public function clearCache()
    {
        Cache::flush();
        
        return response()->json([
            'success' => true,
            'message' => 'Heatmap cache cleared'
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Course;
use App\Models\Brigade;
use App\Models\Position;
use App\Models\EmployeeCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeatMapController extends Controller
{
    /**
     * 3.1 GET /heatmap - Данные для тепловой карты
     */
    public function getHeatmapData(Request $request)
    {
        try {
            // Получаем все типы обучений (курсы)
            $courses = Course::with('category')
                ->orderBy('category_id')
                ->orderBy('name')
                ->get();
            
            $trainingTypes = $courses->map(function($course) {
                return [
                    'id' => 'course_' . $course->id,
                    'name' => $course->name,
                    'shortName' => $this->getSafeShortName($course->name),
                    'category' => $course->category?->name ?? 'Без категории',
                    'categoryId' => $course->category_id
                ];
            });
            
            // Получаем сотрудников с их обучениями
            $query = Employee::with(['position', 'brigade', 'employeeCourses.course']);
            
            // Фильтр по бригаде
            if ($request->has('brigade_id') && $request->brigade_id) {
                $query->where('brigade_id', $request->brigade_id);
            }
            
            // Фильтр по должности
            if ($request->has('position_id') && $request->position_id) {
                $query->where('position_id', $request->position_id);
            }
            
            // Фильтр по статусу сотрудника
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            // Поиск по ФИО
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('full_name', 'LIKE', "%{$search}%");
            }
            
            $employees = $query->get();
            
            // Получаем все бригады для фильтров
            $brigades = Brigade::orderBy('name')->pluck('name')->toArray();
            
            $formattedEmployees = [];
            foreach ($employees as $employee) {
                // Создаем массив статусов по каждому курсу
                $trainingsStatus = [];
                $overallStatus = 'active';
                
                foreach ($courses as $course) {
                    $employeeCourse = $employee->employeeCourses->firstWhere('course_id', $course->id);
                    
                    if (!$employeeCourse) {
                        $status = 'noData';
                    } else {
                        $status = $employeeCourse->status;
                        
                        // Определяем общий статус сотрудника
                        if ($status === 'expired') {
                            $overallStatus = 'expired';
                        } elseif ($status === 'expiring' && $overallStatus !== 'expired') {
                            $overallStatus = 'expiring';
                        } elseif ($status === 'required' && $overallStatus !== 'expired' && $overallStatus !== 'expiring') {
                            $overallStatus = 'required';
                        }
                    }
                    
                    $trainingsStatus['course_' . $course->id] = $status;
                }
                
                // Добавляем дополнительные поля для удобства
                $trainingsStatus['_overall'] = $overallStatus;
                
                $formattedEmployees[] = [
                    'id' => $employee->id,
                    'name' => $this->sanitizeString($employee->full_name),
                    'position' => $this->sanitizeString($employee->position?->name ?? 'Не указана'),
                    'positionId' => $employee->position_id,
                    'brigade' => $this->sanitizeString($employee->brigade?->name ?? 'Не указана'),
                    'brigadeId' => $employee->brigade_id,
                    'status' => $employee->status,
                    'trainings' => $trainingsStatus,
                    'overallStatus' => $overallStatus
                ];
            }
            
            return response()->json([
                'employees' => $formattedEmployees,
                'trainingTypes' => $trainingTypes,
                'brigades' => $brigades,
                'totalEmployees' => $employees->count(),
                'totalTrainings' => $courses->count()
            ])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
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
            $employee = Employee::with(['position', 'brigade', 'employeeCourses.course'])
                ->findOrFail($employeeId);
            
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
            
            // Добавляем недостающие обучения (noData)
            $allCourses = Course::all();
            $existingCourseIds = $employee->employeeCourses->pluck('course_id')->toArray();
            
            foreach ($allCourses as $course) {
                if (!in_array($course->id, $existingCourseIds)) {
                    $trainings[] = [
                        'id' => 'course_' . $course->id,
                        'name' => $this->sanitizeString($course->name),
                        'status' => 'noData',
                        'expiresDate' => null,
                        'assignedDate' => null,
                        'completedDate' => null,
                        'daysLeft' => null,
                        'certificateUrl' => null
                    ];
                }
            }
            
            // Сортируем обучения по статусу и названию
            usort($trainings, function($a, $b) {
                $statusOrder = [
                    'expired' => 0,
                    'expiring' => 1,
                    'active' => 2,
                    'required' => 3,
                    'noData' => 4
                ];
                
                $orderA = $statusOrder[$a['status']] ?? 5;
                $orderB = $statusOrder[$b['status']] ?? 5;
                
                if ($orderA === $orderB) {
                    return strcmp($a['name'], $b['name']);
                }
                
                return $orderA - $orderB;
            });
            
            // Статистика по обучениям сотрудника
            $stats = [
                'total' => count($trainings),
                'active' => count(array_filter($trainings, fn($t) => $t['status'] === 'active')),
                'expiring' => count(array_filter($trainings, fn($t) => $t['status'] === 'expiring')),
                'expired' => count(array_filter($trainings, fn($t) => $t['status'] === 'expired')),
                'required' => count(array_filter($trainings, fn($t) => $t['status'] === 'required')),
                'noData' => count(array_filter($trainings, fn($t) => $t['status'] === 'noData'))
            ];
            
            $compliancePercentage = $stats['total'] > 0 
                ? round((($stats['active'] + $stats['expiring']) / $stats['total']) * 100)
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
            $totalEmployees = Employee::count();
            
            // Общая статистика по обучениям
            $totalTrainings = EmployeeCourse::count();
            
            $statusCounts = [
                'active' => EmployeeCourse::where('status', 'active')->count(),
                'expiring' => EmployeeCourse::where('status', 'expiring')->count(),
                'expired' => EmployeeCourse::where('status', 'expired')->count(),
                'required' => EmployeeCourse::where('status', 'required')->count(),
                'noData' => Employee::doesntHave('employeeCourses')->count()
            ];
            
            // Статистика по бригадам
            $brigades = Brigade::with(['employees.employeeCourses'])->get();
            $byBrigade = [];
            
            foreach ($brigades as $brigade) {
                $employees = $brigade->employees;
                $employeeIds = $employees->pluck('id');
                
                $active = EmployeeCourse::whereIn('employee_id', $employeeIds)
                    ->where('status', 'active')
                    ->count();
                $expiring = EmployeeCourse::whereIn('employee_id', $employeeIds)
                    ->where('status', 'expiring')
                    ->count();
                $expired = EmployeeCourse::whereIn('employee_id', $employeeIds)
                    ->where('status', 'expired')
                    ->count();
                
                // Сотрудники без данных
                $noDataEmployees = $employees->filter(function($employee) {
                    return $employee->employeeCourses->count() === 0;
                })->count();
                
                $totalTrainingsInBrigade = $active + $expiring + $expired;
                
                $byBrigade[$this->sanitizeString($brigade->name)] = [
                    'total' => $employees->count(),
                    'totalTrainings' => $totalTrainingsInBrigade,
                    'active' => $active,
                    'expiring' => $expiring,
                    'expired' => $expired,
                    'noData' => $noDataEmployees
                ];
            }
            
            // Статистика по должностям
            $positions = Position::with(['employees.employeeCourses'])->get();
            $byPosition = [];
            
            foreach ($positions as $position) {
                $employees = $position->employees;
                $employeeIds = $employees->pluck('id');
                
                $active = EmployeeCourse::whereIn('employee_id', $employeeIds)
                    ->where('status', 'active')
                    ->count();
                $expiring = EmployeeCourse::whereIn('employee_id', $employeeIds)
                    ->where('status', 'expiring')
                    ->count();
                $expired = EmployeeCourse::whereIn('employee_id', $employeeIds)
                    ->where('status', 'expired')
                    ->count();
                
                $byPosition[$this->sanitizeString($position->name)] = [
                    'total' => $employees->count(),
                    'active' => $active,
                    'expiring' => $expiring,
                    'expired' => $expired
                ];
            }
            
            // Процент соответствия в целом
            $compliantEmployees = Employee::whereDoesntHave('employeeCourses', function($query) {
                $query->where('status', 'expired');
            })->count();
            
            $compliancePercentage = $totalEmployees > 0 
                ? round(($compliantEmployees / $totalEmployees) * 100)
                : 0;
            
            return response()->json([
                'totalEmployees' => $totalEmployees,
                'totalTrainings' => $totalTrainings,
                'statusCounts' => $statusCounts,
                'byBrigade' => $byBrigade,
                'byPosition' => $byPosition,
                'compliancePercentage' => $compliancePercentage,
                'compliantEmployees' => $compliantEmployees,
                'nonCompliantEmployees' => $totalEmployees - $compliantEmployees
            ])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
            
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
            
            // Получаем данные для экспорта
            $courses = Course::orderBy('name')->get();
            
            $query = Employee::with(['position', 'brigade', 'employeeCourses.course']);
            
            if ($brigadeId) {
                $query->where('brigade_id', $brigadeId);
            }
            
            if ($positionId) {
                $query->where('position_id', $positionId);
            }
            
            $employees = $query->get();
            
            // Формируем данные для CSV
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
                    $employeeCourse = $employee->employeeCourses->firstWhere('course_id', $course->id);
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
                    
                    // Добавляем BOM для UTF-8
                    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                    
                    // Заголовки
                    if (!empty($data)) {
                        fputcsv($file, array_keys($data[0]));
                    }
                    
                    // Данные
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
            $brigades = Brigade::orderBy('name')
                ->get()
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
                ->get()
                ->map(function($position) {
                    return [
                        'id' => $position->id,
                        'name' => $this->sanitizeString($position->name),
                        'employeeCount' => $position->employees()->count()
                    ];
                });
            
            // Категории курсов
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
            
            return response()->json([
                'brigades' => $brigades,
                'statuses' => $statuses,
                'positions' => $positions,
                'categories' => $categories
            ])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
            
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
            $brigadeId = $request->get('brigade_id');
            $positionId = $request->get('position_id');
            
            $courses = Course::with('category')
                ->orderBy('category_id')
                ->orderBy('name')
                ->get();
            
            $query = Employee::with(['position', 'brigade', 'employeeCourses.course']);
            
            if ($brigadeId) {
                $query->where('brigade_id', $brigadeId);
            }
            
            if ($positionId) {
                $query->where('position_id', $positionId);
            }
            
            $employees = $query->get();
            
            $matrix = [];
            $rowStats = [];
            
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
                    $employeeCourse = $employee->employeeCourses->firstWhere('course_id', $course->id);
                    
                    if (!$employeeCourse) {
                        $status = 'noData';
                        $noDataCount++;
                    } else {
                        $status = $employeeCourse->status;
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
            $columnStats = [];
            foreach ($courses as $course) {
                $statusCounts = [
                    'active' => 0,
                    'expiring' => 0,
                    'expired' => 0,
                    'required' => 0,
                    'noData' => 0
                ];
                
                foreach ($employees as $employee) {
                    $employeeCourse = $employee->employeeCourses->firstWhere('course_id', $course->id);
                    $status = $employeeCourse ? $employeeCourse->status : 'noData';
                    $statusCounts[$status]++;
                }
                
                $totalEmployees = $employees->count();
                $columnStats['course_' . $course->id] = [
                    'name' => $course->name,
                    'category' => $course->category?->name,
                    'stats' => $statusCounts,
                    'complianceRate' => $totalEmployees > 0 
                        ? round((($statusCounts['active'] + $statusCounts['expiring']) / $totalEmployees) * 100)
                        : 0
                ];
            }
            
            $averageCompliance = count($rowStats) > 0 
                ? round(array_sum($rowStats) / count($rowStats))
                : 0;
            
            // Форматируем курсы для ответа
            $formattedCourses = $courses->map(function($course) {
                return [
                    'id' => 'course_' . $course->id,
                    'name' => $course->name,
                    'category' => $course->category?->name
                ];
            });
            
            return response()->json([
                'courses' => $formattedCourses,
                'matrix' => $matrix,
                'columnStats' => $columnStats,
                'averageCompliance' => $averageCompliance,
                'totalEmployees' => $employees->count(),
                'totalCourses' => $courses->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch competence matrix',
                'message' => $e->getMessage()
            ], 500);
        }
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
        
        // Берем первые буквы слов (латиница или кириллица)
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
        
        // Удаляем некорректные UTF-8 символы
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // Заменяем специальные символы
        $string = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
        
        // Удаляем лишние пробелы
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
}
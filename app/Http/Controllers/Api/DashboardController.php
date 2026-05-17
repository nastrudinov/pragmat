<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mobilization;
use App\Models\MobilizationStage;
use App\Models\Employee;
use App\Models\EmployeeCourse;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * 10.1 GET /dashboard/stats - Основная статистика
     */
    public function getStats(Request $request)
    {
        try {
            // Статистика по процессам мобилизации
            $totalProcesses = Mobilization::count();
            $activeProcesses = Mobilization::where('status', 'active')->count();
            $blockedProcesses = Mobilization::where('status', 'blocked')->count();
            $completedProcesses = Mobilization::where('status', 'completed')->count();
            
            // Расчет общего прогресса
            $overallProgress = $this->calculateOverallProgress();
            
            // Дополнительная статистика по сотрудникам
            $totalEmployees = Employee::count();
            $activeEmployees = Employee::where('status', 'active')->count();
            
            // Статистика по компетенциям
            $totalCourses = Course::count();
            $completedTrainings = EmployeeCourse::where('status', 'active')
                ->whereNotNull('completed_date')
                ->count();
            $expiredTrainings = EmployeeCourse::where('status', 'expired')->count();
            
            // Соответствие требованиям
            $compliantEmployees = Employee::whereDoesntHave('employeeCourses', function($query) {
                $query->where('status', 'expired');
            })->count();
            
            $compliancePercentage = $totalEmployees > 0 
                ? round(($compliantEmployees / $totalEmployees) * 100) 
                : 0;
            
            // Статистика по этапам мобилизаций
            $stageDistribution = DB::table('mobilizations')
                ->join('mobilization_stages', 'mobilizations.current_stage_id', '=', 'mobilization_stages.id')
                ->select('mobilization_stages.name', DB::raw('count(*) as count'))
                ->where('mobilizations.status', 'active')
                ->groupBy('mobilization_stages.id', 'mobilization_stages.name')
                ->get();
            
            return response()->json([
                'totalProcesses' => $totalProcesses,
                'activeProcesses' => $activeProcesses,
                'blockedProcesses' => $blockedProcesses,
                'completedProcesses' => $completedProcesses,
                'overallProgress' => $overallProgress,
                'additionalStats' => [
                    'totalEmployees' => $totalEmployees,
                    'activeEmployees' => $activeEmployees,
                    'totalCourses' => $totalCourses,
                    'completedTrainings' => $completedTrainings,
                    'expiredTrainings' => $expiredTrainings,
                    'compliantEmployees' => $compliantEmployees,
                    'compliancePercentage' => $compliancePercentage
                ],
                'stageDistribution' => $stageDistribution
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch dashboard stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 10.2 GET /dashboard/upcoming - Ближайшие события
     */
    public function getUpcomingEvents(Request $request)
    {
        try {
            // Получаем предстоящие обучения
            $trainings = $this->getUpcomingTrainings();
            
            // Получаем истекающие сертификаты
            $expiringCertificates = $this->getExpiringCertificates();
            
            // Получаем активные мобилизации
            $activeMobilizations = $this->getActiveMobilizations();
            
            // Получаем задачи на ближайшую неделю
            $upcomingTasks = $this->getUpcomingTasks();
            
            return response()->json([
                'trainings' => $trainings,
                'expiringCertificates' => $expiringCertificates,
                'activeMobilizations' => $activeMobilizations,
                'upcomingTasks' => $upcomingTasks
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch upcoming events',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить прогресс по всем процессам
     */
    private function calculateOverallProgress()
    {
        $total = Mobilization::count();
        if ($total === 0) return 0;
        
        // Веса для разных статусов
        $statusWeights = [
            'active' => 50,
            'blocked' => 25,
            'completed' => 100
        ];
        
        $totalProgress = Mobilization::all()->sum(function($mobilization) use ($statusWeights) {
            $baseWeight = $statusWeights[$mobilization->status] ?? 0;
            
            // Дополнительный прогресс в зависимости от этапа
            $stageProgress = 0;
            if ($mobilization->currentStage) {
                $maxStageOrder = MobilizationStage::max('sort_order') ?? 1;
                $stageProgress = ($mobilization->currentStage->sort_order / $maxStageOrder) * 50;
            }
            
            return min(100, $baseWeight + $stageProgress);
        });
        
        return round($totalProgress / $total);
    }
    
    /**
     * Получить предстоящие обучения
     */
    private function getUpcomingTrainings()
    {
        try {
            $today = now();
            $nextMonth = now()->addDays(30);
            
            // Группируем обучения по датам
            $trainings = EmployeeCourse::with(['course', 'employee'])
                ->where('status', 'active')
                ->whereNotNull('assigned_date')
                ->whereBetween('assigned_date', [$today, $nextMonth])
                ->orderBy('assigned_date')
                ->get()
                ->groupBy('assigned_date')
                ->map(function($items, $date) {
                    return [
                        'date' => $date,
                        'title' => $items->first()->course?->name ?? 'Обучение',
                        'employeeCount' => $items->count(),
                        'status' => 'confirmed',
                        'courses' => $items->groupBy('course_id')->map(function($courseItems) {
                            return [
                                'name' => $courseItems->first()->course?->name ?? 'Неизвестный курс',
                                'count' => $courseItems->count()
                            ];
                        })->values()
                    ];
                })
                ->values()
                ->take(10);
            
            return $trainings;
            
        } catch (\Exception $e) {
            // Логируем ошибку, но возвращаем пустой массив
            \Log::error('Failed to get upcoming trainings: ' . $e->getMessage());
            return collect();
        }
    }
    
    /**
     * Получить истекающие сертификаты
     */
    private function getExpiringCertificates()
    {
        try {
            $today = now();
            $nextTwoWeeks = now()->addDays(14);
            
            $expiringCertificates = EmployeeCourse::with(['employee', 'course'])
                ->where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [$today, $nextTwoWeeks])
                ->orderBy('expiration_date')
                ->get()
                ->map(function($certificate) {
                    $daysLeft = now()->diffInDays($certificate->expiration_date, false);
                    
                    return [
                        'employeeName' => $certificate->employee->full_name ?? 'Неизвестный сотрудник',
                        'employeeId' => $certificate->employee->id ?? null,
                        'certificate' => $certificate->course?->name ?? 'Сертификат',
                        'courseId' => $certificate->course_id,
                        'daysLeft' => max(0, $daysLeft),
                        'expirationDate' => $certificate->expiration_date->format('Y-m-d'),
                        'status' => $this->getExpiryStatus($daysLeft)
                    ];
                });
            
            return $expiringCertificates;
            
        } catch (\Exception $e) {
            \Log::error('Failed to get expiring certificates: ' . $e->getMessage());
            return collect();
        }
    }

    
    /**
     * Получить активные мобилизации
     */
    private function getActiveMobilizations()
    {
        try {
            $activeMobilizations = Mobilization::with(['currentStage', 'mobilizationEmployees'])
                ->where('status', 'active')
                ->orderBy('start_date')
                ->take(5)
                ->get()
                ->map(function($mobilization) {
                    $totalEmployees = $mobilization->mobilizationEmployees->count();
                    $completedStages = $mobilization->stageHistories()
                        ->where('status', 'completed')
                        ->count();
                    $totalStages = MobilizationStage::count();
                    
                    $progress = $totalStages > 0 
                        ? round(($completedStages / $totalStages) * 100) 
                        : 0;
                    
                    return [
                        'id' => $mobilization->id,
                        'title' => $mobilization->title ?? 'Без названия',
                        'currentStage' => $mobilization->currentStage?->name ?? 'Не указан',
                        'startDate' => $mobilization->start_date?->format('Y-m-d'),
                        'endDate' => $mobilization->end_date?->format('Y-m-d'),
                        'employeesCount' => $totalEmployees,
                        'progress' => $progress,
                        'status' => $mobilization->status
                    ];
                });
            
            return $activeMobilizations;
            
        } catch (\Exception $e) {
            \Log::error('Failed to get active mobilizations: ' . $e->getMessage());
            return collect();
        }
    }
    
    /**
     * Получить задачи на ближайшее время
     */
    private function getUpcomingTasks()
    {
        try {
            $tasks = collect();
            
            // Задачи по истекающим сертификатам
            $expiringCount = EmployeeCourse::where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [now(), now()->addDays(30)])
                ->count();
            
            if ($expiringCount > 0) {
                $tasks->push([
                    'type' => 'certificate_renewal',
                    'title' => 'Обновление сертификатов',
                    'count' => $expiringCount,
                    'priority' => 'high',
                    'deadline' => now()->addDays(30)->format('Y-m-d')
                ]);
            }
            
            // Задачи по новым мобилизациям
            $newMobilizations = Mobilization::where('status', 'active')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
            
            if ($newMobilizations > 0) {
                $tasks->push([
                    'type' => 'mobilization_review',
                    'title' => 'Проверка новых мобилизаций',
                    'count' => $newMobilizations,
                    'priority' => 'medium',
                    'deadline' => now()->addDays(3)->format('Y-m-d')
                ]);
            }
            
            // Задачи по нераспределенным сотрудникам
            $unassignedEmployees = Employee::whereDoesntHave('mobilizationEmployees')
                ->where('status', 'active')
                ->count();
            
            if ($unassignedEmployees > 0) {
                $tasks->push([
                    'type' => 'employee_assignment',
                    'title' => 'Назначение сотрудников на мобилизации',
                    'count' => $unassignedEmployees,
                    'priority' => 'medium',
                    'deadline' => now()->addDays(7)->format('Y-m-d')
                ]);
            }
            
            // Задачи по просроченным обучениям
            $expiredTrainings = EmployeeCourse::where('status', 'expired')
                ->count();
            
            if ($expiredTrainings > 0) {
                $tasks->push([
                    'type' => 'expired_training',
                    'title' => 'Просроченные обучения',
                    'count' => $expiredTrainings,
                    'priority' => 'high',
                    'deadline' => now()->addDays(7)->format('Y-m-d')
                ]);
            }
            
            return $tasks;
            
        } catch (\Exception $e) {
            \Log::error('Failed to get upcoming tasks: ' . $e->getMessage());
            return collect();
        }
    }
    
    /**
     * Получить статус истечения
     */
    private function getExpiryStatus($daysLeft)
    {
        if ($daysLeft <= 0) return 'expired';
        if ($daysLeft <= 3) return 'critical';
        if ($daysLeft <= 7) return 'urgent';
        if ($daysLeft <= 14) return 'warning';
        return 'normal';
    }
    
    /**
     * Дополнительный метод: Статистика по месяцам
     */
    public function getMonthlyStats(Request $request)
    {
        try {
            $year = $request->get('year', now()->year);
            
            // Статистика по мобилизациям по месяцам
            $mobilizationsByMonth = DB::table('mobilizations')
                ->select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereYear('created_at', $year)
                ->groupBy(DB::raw('MONTH(created_at)'))
                ->get()
                ->keyBy('month');
            
            // Статистика по обучениям по месяцам
            $trainingsByMonth = DB::table('employee_courses')
                ->select(
                    DB::raw('MONTH(completed_date) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereYear('completed_date', $year)
                ->whereNotNull('completed_date')
                ->groupBy(DB::raw('MONTH(completed_date)'))
                ->get()
                ->keyBy('month');
            
            $months = range(1, 12);
            $monthlyStats = [];
            
            foreach ($months as $month) {
                $monthlyStats[] = [
                    'month' => $month,
                    'monthName' => date('F', mktime(0, 0, 0, $month, 1)),
                    'mobilizations' => $mobilizationsByMonth[$month]->count ?? 0,
                    'trainings' => $trainingsByMonth[$month]->count ?? 0
                ];
            }
            
            return response()->json([
                'year' => $year,
                'stats' => $monthlyStats
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch monthly stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Дополнительный метод: Статистика по компетенциям
     */
    public function getCompetenceStats()
    {
        try {
            // Статистика по категориям курсов
            $categoryStats = DB::table('course_categories')
                ->leftJoin('courses', 'course_categories.id', '=', 'courses.category_id')
                ->leftJoin('employee_courses', 'courses.id', '=', 'employee_courses.course_id')
                ->select(
                    'course_categories.name',
                    DB::raw('COUNT(DISTINCT courses.id) as total_courses'),
                    DB::raw('COUNT(DISTINCT employee_courses.id) as total_assignments'),
                    DB::raw('SUM(CASE WHEN employee_courses.status = "active" THEN 1 ELSE 0 END) as active_count'),
                    DB::raw('SUM(CASE WHEN employee_courses.status = "expired" THEN 1 ELSE 0 END) as expired_count')
                )
                ->groupBy('course_categories.id', 'course_categories.name')
                ->get();
            
            // Топ-5 самых востребованных курсов
            $topCourses = EmployeeCourse::with('course')
                ->select('course_id', DB::raw('COUNT(*) as count'))
                ->groupBy('course_id')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->map(function($item) {
                    return [
                        'course_id' => $item->course_id,
                        'course_name' => $item->course?->name,
                        'assignments' => $item->count
                    ];
                });
            
            return response()->json([
                'byCategory' => $categoryStats,
                'topCourses' => $topCourses
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch competence stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Дополнительный метод: Дашборд руководителя
     */
    public function getManagerDashboard(Request $request)
    {
        try {
            $managerId = $request->user()?->employee_id;
            
            if (!$managerId) {
                return response()->json([
                    'error' => 'Manager not identified'
                ], 401);
            }
            
            // Получаем бригаду руководителя
            $manager = Employee::with('brigade')->find($managerId);
            
            if (!$manager || !$manager->brigade) {
                return response()->json([
                    'error' => 'No brigade assigned to manager'
                ], 404);
            }
            
            $brigadeId = $manager->brigade->id;
            
            // Сотрудники бригады
            $brigadeEmployees = Employee::where('brigade_id', $brigadeId)
                ->where('status', 'active')
                ->get();
            
            $employeeIds = $brigadeEmployees->pluck('id');
            
            // Статистика по обучениям бригады
            $totalTrainings = EmployeeCourse::whereIn('employee_id', $employeeIds)->count();
            $expiredTrainings = EmployeeCourse::whereIn('employee_id', $employeeIds)
                ->where('status', 'expired')
                ->count();
            $activeTrainings = EmployeeCourse::whereIn('employee_id', $employeeIds)
                ->where('status', 'active')
                ->count();
            
            // Соответствие требованиям
            $compliantEmployees = 0;
            foreach ($brigadeEmployees as $employee) {
                $hasExpired = $employee->employeeCourses->contains('status', 'expired');
                if (!$hasExpired) {
                    $compliantEmployees++;
                }
            }
            
            $compliancePercentage = $brigadeEmployees->count() > 0
                ? round(($compliantEmployees / $brigadeEmployees->count()) * 100)
                : 0;
            
            // Ближайшие истечения сертификатов по бригаде
            $expiringSoon = EmployeeCourse::with(['employee', 'course'])
                ->whereIn('employee_id', $employeeIds)
                ->where('status', 'active')
                ->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [now(), now()->addDays(30)])
                ->orderBy('expiration_date')
                ->limit(10)
                ->get()
                ->map(function($cert) {
                    return [
                        'employee' => $cert->employee->full_name,
                        'course' => $cert->course?->name,
                        'expirationDate' => $cert->expiration_date->format('Y-m-d'),
                        'daysLeft' => now()->diffInDays($cert->expiration_date, false)
                    ];
                });
            
            return response()->json([
                'brigade' => [
                    'id' => $brigadeId,
                    'name' => $manager->brigade->name,
                    'employeeCount' => $brigadeEmployees->count()
                ],
                'stats' => [
                    'totalTrainings' => $totalTrainings,
                    'activeTrainings' => $activeTrainings,
                    'expiredTrainings' => $expiredTrainings,
                    'compliantEmployees' => $compliantEmployees,
                    'compliancePercentage' => $compliancePercentage
                ],
                'expiringCertificates' => $expiringSoon
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch manager dashboard',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
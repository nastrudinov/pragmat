<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\MobilizationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BrigadeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TrainingController;
use App\Http\Controllers\Api\HeatMapController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\TrainingEventController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BulkTrainingController;

use Illuminate\Http\Request;

Route::prefix('test')->group(function () {
    // Простая проверка
    Route::get('/ping', function () {
        return ['pong' => now()->toDateTimeString()];
    });
    
    // Проверка БД
    Route::get('/db', function () {
        try {
            DB::connection()->getPdo();
            return ['database' => 'connected'];
        } catch (\Exception $e) {
            return response()->json(['database' => 'error', 'message' => $e->getMessage()], 500);
        }
    });
    
    // Проверка конфигурации
    Route::get('/config', function () {
        return [
            'app_name' => config('app.name'),
            'env' => app()->environment(),
            'timezone' => config('app.timezone'),
            'debug' => config('app.debug')
        ];
    });
});

// ==================== ПУБЛИЧНЫЕ МАРШРУТЫ (без авторизации) ====================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
});

// ==================== ЗАЩИЩЕННЫЕ МАРШРУТЫ (требуют токен) ====================
Route::middleware(['auth:sanctum'])->group(function () {
    
    // ========== АУТЕНТИФИКАЦИЯ ==========
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
    
    // ========== СМЕНА ПАРОЛЯ (ДЛЯ ТЕКУЩЕГО ПОЛЬЗОВАТЕЛЯ) ==========
    Route::put('/users/password', [UserController::class, 'changePassword']);
    Route::post('/users/password/reset', [UserController::class, 'forgotPassword']);
    
    // ========== УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ (ТОЛЬКО АДМИН) ==========
    Route::prefix('users')->middleware(['role:admin'])->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/roles', [UserController::class, 'getRoles']);
        Route::get('/employees-without-account', [UserController::class, 'getEmployeesWithoutAccount']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::put('/{id}/password', [UserController::class, 'resetPassword']);
    });

    Route::post('/training-events/clear-cache', [TrainingEventController::class, 'clearCache'])->middleware(['role:admin']);
    
    // ========== СОТРУДНИКИ ==========
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index']);
        Route::get('/search', [EmployeeController::class, 'search']);
        Route::get('/statistics', [EmployeeController::class, 'getStatistics']);
        Route::get('/compliance', [EmployeeController::class, 'getCompliance']);
        Route::get('/brigade/{brigadeId}', [EmployeeController::class, 'getByBrigade']);
        Route::post('/', [EmployeeController::class, 'store']);
        Route::get('/{id}', [EmployeeController::class, 'show']);
        Route::put('/{id}', [EmployeeController::class, 'update']);
        Route::delete('/{id}', [EmployeeController::class, 'destroy']);
        Route::post('/compliance/selected', [EmployeeController::class, 'getComplianceForSelected']);
        Route::post('/{id}/change-position', [EmployeeController::class, 'changePosition']);
    });
    
    // ========== ДОЛЖНОСТИ ==========
    Route::prefix('positions')->group(function () {
        Route::get('/', [PositionController::class, 'index']);
        Route::get('/categories', [PositionController::class, 'getCategories']);
        Route::post('/', [PositionController::class, 'store']);
        Route::get('/{id}', [PositionController::class, 'show']);
        Route::get('/{id}/employees', [PositionController::class, 'getEmployees']);
        Route::get('/{id}/statistics', [PositionController::class, 'getStatistics']);
        Route::get('/{id}/course-requirements', [PositionController::class, 'getCourseRequirements']);
        Route::put('/{id}', [PositionController::class, 'update']);
        Route::delete('/{id}', [PositionController::class, 'destroy']);
    });
    
    // ========== БРИГАДЫ ==========
    Route::prefix('brigades')->group(function () {
        Route::get('/', [BrigadeController::class, 'index']);
        Route::get('/statistics', [BrigadeController::class, 'getStatistics']);
        Route::post('/', [BrigadeController::class, 'store']);
        Route::get('/{id}', [BrigadeController::class, 'show']);
        Route::get('/{id}/members', [BrigadeController::class, 'getMembers']);
        Route::put('/{id}', [BrigadeController::class, 'update']);
        Route::delete('/{id}', [BrigadeController::class, 'destroy']);
        Route::post('/{id}/members', [BrigadeController::class, 'addMember']);
        Route::delete('/{id}/members/{employeeId}', [BrigadeController::class, 'removeMember']);
    });
    
    // ========== ПОДРАЗДЕЛЕНИЯ ==========
    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index']);
        Route::get('/tree', [DepartmentController::class, 'index'])->defaults('tree', true);
        Route::post('/', [DepartmentController::class, 'store']);
        Route::get('/{id}', [DepartmentController::class, 'show']);
        Route::get('/{id}/employees', [DepartmentController::class, 'getEmployees']);
        Route::put('/{id}', [DepartmentController::class, 'update']);
        Route::delete('/{id}', [DepartmentController::class, 'destroy']);
    });
    
    // ========== КУРСЫ ==========
    Route::prefix('courses')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::get('/categories', [CourseController::class, 'getCategories']);
        Route::get('/filters', [CourseController::class, 'getFilters']);
        Route::get('/popular', [CourseController::class, 'getPopularCourses']);  // <-- ПЕРЕНЕСИТЕ СЮДА
        Route::get('/required/{positionId}', [CourseController::class, 'getRequiredCoursesForPosition']);
        Route::get('/required/brigade/{brigadeId}', [CourseController::class, 'getRequiredCoursesForBrigade']);
        Route::post('/', [CourseController::class, 'store']);
        Route::get('/{id}', [CourseController::class, 'show']);  // ЭТО ДОЛЖНО БЫТЬ ПОСЛЕ СПЕЦИФИЧНЫХ МАРШРУТОВ
        Route::put('/{id}', [CourseController::class, 'update']);
        Route::delete('/{id}', [CourseController::class, 'destroy']);
        Route::get('/{courseId}/events', [CourseController::class, 'getCourseEvents']);
        Route::get('/{courseId}/employees', [CourseController::class, 'getCourseEmployees']);
    });
    
    // ========== МАТРИЦА КОМПЕТЕНЦИЙ ==========
    Route::prefix('matrix')->group(function () {
        Route::get('/positions', [CourseController::class, 'getCompetenceMatrix']);
        Route::put('/positions/{positionId}/courses/{courseId}', [CourseController::class, 'assignCourseToPosition']);
        Route::put('/brigades/{brigadeId}/courses/{courseId}', [CourseController::class, 'assignCourseToBrigade']);
    });
    
    // ========== ОБУЧЕНИЯ ==========
    Route::prefix('trainings')->group(function () {
        // Специфичные статические роуты
        Route::get('/employee-courses-summary', [TrainingController::class, 'getEmployeeCoursesSummary']);
        Route::get('/employee-courses-summary-show', [TrainingController::class, 'getEmployeeCoursesSummaryWithIsShow']);
        Route::get('/expired', [TrainingController::class, 'getExpiredTrainings']);
        Route::get('/expiring/{days}', [TrainingController::class, 'getExpiringTrainings']);
        Route::get('/statistics', [TrainingController::class, 'getStatistics']);
        Route::get('/export', [TrainingController::class, 'export']);
        Route::get('/search', [TrainingController::class, 'search']);
        Route::post('/bulk-update-expiration', [BulkTrainingController::class, 'bulkUpdateExpiration']);
        
        // Роуты с параметрами
        Route::get('/employee/{employeeId}', [TrainingController::class, 'getEmployeeTrainings']);
        Route::get('/brigade/{brigadeId}', [TrainingController::class, 'getBrigadeTrainings']);
        
        // Массовое назначение
        Route::post('/assign-to-position', [TrainingController::class, 'assignToPosition']);
        Route::post('/assign-to-brigade', [TrainingController::class, 'assignToBrigade']);
        Route::post('/assign-to-department', [TrainingController::class, 'assignToDepartment']);
        
        // POST роуты
        Route::post('/assign', [TrainingController::class, 'assignTraining']);
        Route::post('/bulk-assign', [TrainingController::class, 'bulkAssign']);
        
        // Dashboard роуты
        Route::prefix('dashboard')->group(function () {
            Route::get('/expired-by-course', [TrainingController::class, 'getExpiredByCourse']);
            Route::get('/expiring-by-period', [TrainingController::class, 'getExpiringByPeriod']);
            Route::get('/expiring-in-month', [TrainingController::class, 'getExpiringInMonth']);
            Route::get('/expiring-in-two-months', [TrainingController::class, 'getExpiringInTwoMonths']);
            Route::get('/summary', [TrainingController::class, 'getDashboardSummary']);
        });
        
        // Роуты с {id} - должны быть последними
        Route::get('/{id}', [TrainingController::class, 'show']);
        Route::put('/{id}/complete', [TrainingController::class, 'completeTraining']);
        Route::put('/{id}/extend', [TrainingController::class, 'extendTraining']);
        Route::put('/{id}/certificate', [TrainingController::class, 'updateCertificateInfo']);
        Route::patch('/{id}/certificate-number', [TrainingController::class, 'updateCertificateNumber']);
        Route::patch('/{id}/regulatory-acts', [TrainingController::class, 'updateRegulatoryActs']);
        Route::delete('/{id}', [TrainingController::class, 'destroy']);
    });
    
    // ========== МЕРОПРИЯТИЯ ==========
    Route::prefix('training-events')->group(function () {
        Route::get('/', [TrainingEventController::class, 'index']);
        Route::get('/calendar', [TrainingEventController::class, 'calendar']);
        Route::post('/', [TrainingEventController::class, 'store']);
        Route::get('/{id}', [TrainingEventController::class, 'show']);
        Route::put('/{id}', [TrainingEventController::class, 'update']);
        Route::delete('/{id}', [TrainingEventController::class, 'destroy']);
        
        // Участники
        Route::post('/{id}/participants', [TrainingEventController::class, 'addParticipants']);
        Route::delete('/{id}/participants', [TrainingEventController::class, 'removeAllParticipants']);
        Route::delete('/{id}/participants/{participantId}', [TrainingEventController::class, 'removeParticipant']);
        Route::post('/{id}/participants/remove-bulk', [TrainingEventController::class, 'removeParticipantsBulk']);
        Route::put('/{id}/participants/{participantId}/status', [TrainingEventController::class, 'updateParticipantStatus']);
    });
    
    // ========== МОБИЛИЗАЦИЯ ==========
    Route::prefix('processes')->group(function () {
        Route::get('/', [MobilizationController::class, 'index']);
        Route::get('/mobilization', [MobilizationController::class, 'getMobilizations']);
        Route::post('/mobilization', [MobilizationController::class, 'storeMobilization']);
        Route::get('/mobilization/{id}', [MobilizationController::class, 'showMobilization']);
        Route::put('/mobilization/{id}/stage', [MobilizationController::class, 'changeStage']);
        Route::put('/mobilization/{id}/status', [MobilizationController::class, 'updateStatus']);
        Route::post('/mobilization/{id}/employees', [MobilizationController::class, 'addEmployees']);
        Route::delete('/mobilization/{id}/employees/{employeeId}', [MobilizationController::class, 'removeEmployee']);
    });
    
    // ========== ТЕПЛОВАЯ КАРТА ==========
    Route::prefix('heatmap')->group(function () {
        Route::get('/', [HeatMapController::class, 'getHeatmapData']);
        Route::get('/summary', [HeatMapController::class, 'getSummary']);
        Route::get('/export', [HeatMapController::class, 'export']);
        Route::get('/filters', [HeatMapController::class, 'getFilters']);
        Route::get('/matrix', [HeatMapController::class, 'getCompetenceMatrix']);
        Route::get('/employee/{employeeId}', [HeatMapController::class, 'getEmployeeData']);
    });
    
    // ========== ДАШБОРД ==========
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'getStats']);
        Route::get('/upcoming', [DashboardController::class, 'getUpcomingEvents']);
        Route::get('/monthly', [DashboardController::class, 'getMonthlyStats']);
        Route::get('/competence', [DashboardController::class, 'getCompetenceStats']);
        Route::get('/manager', [DashboardController::class, 'getManagerDashboard']);
    });
    
    // ========== УВЕДОМЛЕНИЯ ==========
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
    });
    
    // ========== ТЕСТОВЫЙ МАРШРУТ ==========
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// ========== FALLBACK ДЛЯ НЕСУЩЕСТВУЮЩИХ МАРШРУТОВ ==========
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
})->name('fallback');
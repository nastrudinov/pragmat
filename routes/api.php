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

// Маршруты для подразделений (требуют аутентификации)

    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index']);                    // GET /departments
        Route::get('/tree', [DepartmentController::class, 'index'])->defaults('tree', true); // GET /departments/tree
        Route::post('/', [DepartmentController::class, 'store']);                  // POST /departments
        Route::get('/{id}', [DepartmentController::class, 'show']);                // GET /departments/{id}
        Route::get('/{id}/employees', [DepartmentController::class, 'getEmployees']); // GET /departments/{id}/employees
        Route::put('/{id}', [DepartmentController::class, 'update']);              // PUT /departments/{id}
        Route::delete('/{id}', [DepartmentController::class, 'destroy']);          // DELETE /departments/{id}
    });

// Маршруты для сотрудников
Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);              // 4.1 GET /employees
    Route::get('/search', [EmployeeController::class, 'search']);       // 4.4 GET /employees/search
    Route::get('/statistics', [EmployeeController::class, 'getStatistics']); // 4.5 GET /employees/statistics
    Route::get('/compliance', [EmployeeController::class, 'getCompliance']); // 4.6 GET /employees/compliance
    Route::get('/brigade/{brigadeId}', [EmployeeController::class, 'getByBrigade']); // 4.3 GET /employees/brigade/{brigadeId}
    Route::post('/', [EmployeeController::class, 'store']);             // 4.7 POST /employees
    Route::get('/{id}', [EmployeeController::class, 'show']);           // 4.2 GET /employees/{id}
    Route::put('/{id}', [EmployeeController::class, 'update']);         // 4.8 PUT /employees/{id}
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);     // 4.9 DELETE /employees/{id}
});

// Маршруты для должностей
Route::prefix('positions')->group(function () {
    Route::get('/', [PositionController::class, 'index']);                           // 7.1 GET /positions
    Route::get('/categories', [PositionController::class, 'getCategories']);        // 7.2 GET /positions/categories
    Route::post('/', [PositionController::class, 'store']);                          // 7.3 POST /positions
    Route::get('/{id}', [PositionController::class, 'show']);                        // Получение должности по ID
    Route::get('/{id}/employees', [PositionController::class, 'getEmployees']);     // Сотрудники должности с группировкой
    Route::get('/{id}/statistics', [PositionController::class, 'getStatistics']);   // Статистика по должности
    Route::get('/{id}/course-requirements', [PositionController::class, 'getCourseRequirements']); // Требования курсов
    Route::put('/{id}', [PositionController::class, 'update']);                      // 7.4 PUT /positions/{id}
    Route::delete('/{id}', [PositionController::class, 'destroy']);                  // 7.5 DELETE /positions/{id}
});

// Маршруты для процессов мобилизации
Route::prefix('processes')->group(function () {
    Route::get('/', [MobilizationController::class, 'index']);                          // 9.1 GET /processes
    Route::get('/mobilization', [MobilizationController::class, 'getMobilizations']);  // 9.2 GET /processes/mobilization
    Route::post('/mobilization', [MobilizationController::class, 'storeMobilization']); // 9.4 POST /processes/mobilization
    Route::get('/mobilization/{id}', [MobilizationController::class, 'showMobilization']); // 9.3 GET /processes/mobilization/{id}
    Route::put('/mobilization/{id}/stage', [MobilizationController::class, 'changeStage']); // 9.5 PUT /processes/mobilization/{id}/stage
    Route::put('/mobilization/{id}/status', [MobilizationController::class, 'updateStatus']);
    Route::post('/mobilization/{id}/employees', [MobilizationController::class, 'addEmployees']);
    Route::delete('/mobilization/{id}/employees/{employeeId}', [MobilizationController::class, 'removeEmployee']);
});

// Маршруты для дашборда и статистики
Route::prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'getStats']);                    // 10.1 GET /dashboard/stats
    Route::get('/upcoming', [DashboardController::class, 'getUpcomingEvents']);        // 10.2 GET /dashboard/upcoming
    Route::get('/monthly', [DashboardController::class, 'getMonthlyStats']);           // Доп. статистика по месяцам
    Route::get('/competence', [DashboardController::class, 'getCompetenceStats']);     // Доп. статистика компетенций
    Route::get('/manager', [DashboardController::class, 'getManagerDashboard']);       // Доп. дашборд руководителя
});

// Маршруты для бригад
Route::prefix('brigades')->group(function () {
    Route::get('/', [BrigadeController::class, 'index']);                          // 5.1 GET /brigades
    Route::get('/statistics', [BrigadeController::class, 'getStatistics']);       // 5.4 GET /brigades/statistics
    Route::post('/', [BrigadeController::class, 'store']);                        // 5.5 POST /brigades
    Route::get('/{id}', [BrigadeController::class, 'show']);                      // 5.2 GET /brigades/{id}
    Route::get('/{id}/members', [BrigadeController::class, 'getMembers']);        // 5.3 GET /brigades/{id}/members
    Route::put('/{id}', [BrigadeController::class, 'update']);                    // 5.6 PUT /brigades/{id}
    Route::delete('/{id}', [BrigadeController::class, 'destroy']);                // 5.7 DELETE /brigades/{id}
    Route::post('/{id}/members', [BrigadeController::class, 'addMember']);        // 5.8 POST /brigades/{id}/members
    Route::delete('/{id}/members/{employeeId}', [BrigadeController::class, 'removeMember']); // 5.9 DELETE /brigades/{id}/members/{employeeId}
});

// Маршруты для уведомлений
Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']); // 8.3 PUT /notifications/mark-all-read
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']); // 8.4 GET /notifications/unread-count
    Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
});

// Маршруты для обучений
Route::prefix('trainings')->group(function () {
    // ===== СПЕЦИФИЧНЫЕ СТАТИЧЕСКИЕ РОУТЫ (ДОЛЖНЫ БЫТЬ ПЕРВЫМИ) =====
    Route::get('/employee-courses-summary', [TrainingController::class, 'getEmployeeCoursesSummary']);
    Route::get('/expired', [TrainingController::class, 'getExpiredTrainings']);
    Route::get('/expiring/{days}', [TrainingController::class, 'getExpiringTrainings']);
    Route::get('/statistics', [TrainingController::class, 'getStatistics']);
    Route::get('/export', [TrainingController::class, 'export']);
    Route::get('/search', [TrainingController::class, 'search']);
    
    // ===== РОУТЫ С ПАРАМЕТРАМИ =====
    Route::get('/employee/{employeeId}', [TrainingController::class, 'getEmployeeTrainings']);
    Route::get('/brigade/{brigadeId}', [TrainingController::class, 'getBrigadeTrainings']);
    
    // ===== МАССОВОЕ НАЗНАЧЕНИЕ =====
    Route::post('/assign-to-position', [TrainingController::class, 'assignToPosition']);
    Route::post('/assign-to-brigade', [TrainingController::class, 'assignToBrigade']);
    Route::post('/assign-to-department', [TrainingController::class, 'assignToDepartment']);
    
    // ===== POST РОУТЫ =====
    Route::post('/assign', [TrainingController::class, 'assignTraining']);
    Route::post('/bulk-assign', [TrainingController::class, 'bulkAssign']);
    
    // ===== DASHBOARD РОУТЫ =====
    Route::prefix('dashboard')->group(function () {
        Route::get('/expired-by-course', [TrainingController::class, 'getExpiredByCourse']);
        Route::get('/expiring-by-period', [TrainingController::class, 'getExpiringByPeriod']);
        Route::get('/expiring-in-month', [TrainingController::class, 'getExpiringInMonth']);
        Route::get('/expiring-in-two-months', [TrainingController::class, 'getExpiringInTwoMonths']);
        Route::get('/summary', [TrainingController::class, 'getDashboardSummary']);
    });
    
    // ===== РОУТЫ С {ID} - ДОЛЖНЫ БЫТЬ ПОСЛЕДНИМИ =====
    Route::get('/{id}', [TrainingController::class, 'show']);
    Route::put('/{id}/complete', [TrainingController::class, 'completeTraining']);
    Route::put('/{id}/extend', [TrainingController::class, 'extendTraining']);
    Route::put('/{id}/certificate', [TrainingController::class, 'updateCertificateInfo']);
    Route::patch('/{id}/certificate-number', [TrainingController::class, 'updateCertificateNumber']);
    Route::patch('/{id}/regulatory-acts', [TrainingController::class, 'updateRegulatoryActs']);
});

// Маршруты для тепловой карты
Route::prefix('heatmap')->group(function () {
    Route::get('/', [HeatMapController::class, 'getHeatmapData']);              // 3.1 GET /heatmap
    Route::get('/summary', [HeatMapController::class, 'getSummary']);          // 3.3 GET /heatmap/summary
    Route::get('/export', [HeatMapController::class, 'export']);               // 3.4 GET /heatmap/export
    Route::get('/filters', [HeatMapController::class, 'getFilters']);          // 3.5 GET /heatmap/filters
    Route::get('/matrix', [HeatMapController::class, 'getCompetenceMatrix']);  // Доп. матрица компетенций
    Route::get('/employee/{employeeId}', [HeatMapController::class, 'getEmployeeData']); // 3.2 GET /heatmap/employee/{id}
});

// Маршруты для курсов
Route::prefix('courses')->group(function () {
    Route::get('/', [CourseController::class, 'index']);
    Route::get('/categories', [CourseController::class, 'getCategories']);
    Route::get('/filters', [CourseController::class, 'getFilters']);  // Новый маршрут
    Route::get('/required/{positionId}', [CourseController::class, 'getRequiredCoursesForPosition']);
    Route::get('/required/brigade/{brigadeId}', [CourseController::class, 'getRequiredCoursesForBrigade']);
    Route::post('/', [CourseController::class, 'store']);
    Route::get('/{id}', [CourseController::class, 'show']);
    Route::put('/{id}', [CourseController::class, 'update']);
    Route::delete('/{id}', [CourseController::class, 'destroy']);
    Route::get('/{courseId}/events', [CourseController::class, 'getCourseEvents']);
    Route::get('/{courseId}/employees', [CourseController::class, 'getCourseEmployees']);
});

// Маршруты для матрицы компетенций
Route::prefix('matrix')->group(function () {
    Route::get('/positions', [CourseController::class, 'getCompetenceMatrix']);   // 6.7 GET /matrix/positions
    Route::put('/positions/{positionId}/courses/{courseId}', [CourseController::class, 'assignCourseToPosition']); // 6.8 PUT /matrix/positions/{positionId}/courses/{courseId}
    Route::put('/brigades/{brigadeId}/courses/{courseId}', [CourseController::class, 'assignCourseToBrigade']);   // 6.9 PUT /matrix/brigades/{brigadeId}/courses/{courseId}
});

// Маршруты для мероприятий
Route::prefix('training-events')->group(function () {
    Route::get('/', [TrainingEventController::class, 'index']);           // Список мероприятий
    Route::get('/calendar', [TrainingEventController::class, 'calendar']); // Данные для календаря
    Route::post('/', [TrainingEventController::class, 'store']);          // Создание
    Route::get('/{id}', [TrainingEventController::class, 'show']);        // Детали
    Route::put('/{id}', [TrainingEventController::class, 'update']);      // Обновление
    Route::delete('/{id}', [TrainingEventController::class, 'destroy']);  // Удаление
    
    // Участники
    Route::post('/{id}/participants', [TrainingEventController::class, 'addParticipants']);
    Route::delete('/{id}/participants', [TrainingEventController::class, 'removeAllParticipants']); 
    // Удалить всех
    Route::delete('/{id}/participants/{participantId}', [TrainingEventController::class, 'removeParticipant']); // Удалить одного
    Route::post('/{id}/participants/remove-bulk', [TrainingEventController::class, 'removeParticipantsBulk']); // Массовое удаление
    Route::put('/{id}/participants/{participantId}/status', [TrainingEventController::class, 'updateParticipantStatus']);

});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

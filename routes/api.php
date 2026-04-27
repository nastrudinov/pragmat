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
    Route::get('/{id}/employees', [PositionController::class, 'getEmployees']);     // Сотрудники должности
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
    Route::get('/expired', [TrainingController::class, 'getExpiredTrainings']);           // 2.1
    Route::get('/expiring/{days}', [TrainingController::class, 'getExpiringTrainings']); // 2.2
    Route::get('/statistics', [TrainingController::class, 'getStatistics']);             // 2.10
    Route::get('/export', [TrainingController::class, 'export']);                        // 2.11
    Route::get('/search', [TrainingController::class, 'search']);                        // 2.12
    
    Route::get('/employee/{employeeId}', [TrainingController::class, 'getEmployeeTrainings']); // 2.4
    Route::get('/brigade/{brigadeId}', [TrainingController::class, 'getBrigadeTrainings']);   // 2.5
    
    Route::post('/assign', [TrainingController::class, 'assignTraining']);               // 2.8
    Route::post('/bulk-assign', [TrainingController::class, 'bulkAssign']);              // 2.9
    
    Route::get('/{id}', [TrainingController::class, 'show']);                            // 2.3
    Route::put('/{id}/complete', [TrainingController::class, 'completeTraining']);       // 2.6
    Route::put('/{id}/extend', [TrainingController::class, 'extendTraining']);           // 2.7
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

// Маршруты для курсов и матрицы компетенций
Route::prefix('courses')->group(function () {
    Route::get('/', [CourseController::class, 'index']);                           // 6.1 GET /courses
    Route::get('/categories', [CourseController::class, 'getCategories']);        // 6.2 GET /courses/categories
    Route::post('/', [CourseController::class, 'store']);                         // 6.4 POST /courses
    Route::get('/{id}', [CourseController::class, 'show']);                       // Детали курса
    Route::get('/required/{positionId}', [CourseController::class, 'getRequiredCoursesForPosition']); // 6.3 GET /courses/required/{positionId}
    Route::put('/{id}', [CourseController::class, 'update']);                     // 6.5 PUT /courses/{id}
    Route::delete('/{id}', [CourseController::class, 'destroy']);                 // 6.6 DELETE /courses/{id}
});

// Маршруты для матрицы компетенций
Route::prefix('matrix')->group(function () {
    Route::get('/positions', [CourseController::class, 'getCompetenceMatrix']);   // 6.7 GET /matrix/positions
    Route::put('/positions/{positionId}/courses/{courseId}', [CourseController::class, 'assignCourseToPosition']); // 6.8 PUT /matrix/positions/{positionId}/courses/{courseId}
    Route::put('/brigades/{brigadeId}/courses/{courseId}', [CourseController::class, 'assignCourseToBrigade']);   // 6.9 PUT /matrix/brigades/{brigadeId}/courses/{courseId}
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

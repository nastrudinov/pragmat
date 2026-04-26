<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmployeeController;


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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

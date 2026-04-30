<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Position;
use App\Models\Brigade;
use App\Models\PositionCourseRequirement;
use App\Models\BrigadeCourseRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    /**
     * 6.1 GET /courses - Список курсов
     */
    public function index(Request $request)
    {
        try {
            $query = Course::with('category');
            
            // Фильтр по категории
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            
            // Поиск по названию
            if ($request->has('search')) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            }
            
            // Сортировка
            $sortField = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);
            
            $courses = $query->get();
            
            $formattedCourses = $courses->map(function($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'category' => $course->category?->name ?? 'Без категории',
                    'category_id' => $course->category_id,
                    'duration' => $course->duration_hours ? $course->duration_hours . ' часов' : 'Не указано',
                    'duration_hours' => $course->duration_hours,
                    'periodicity' => $course->periodicity_months ? $course->periodicity_months . ' ' . $this->getPeriodicityText($course->periodicity_months) : 'Не указано',
                    'periodicity_months' => $course->periodicity_months,
                    'description' => $course->description,
                    'created_at' => $course->created_at?->toISOString()
                ];
            });
            
            return response()->json([
                'courses' => $formattedCourses
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch courses',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 6.2 GET /courses/categories - Категории курсов
     */
    public function getCategories()
    {
        try {
            $categories = CourseCategory::orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'sort_order' => $category->sort_order,
                        'courses_count' => $category->courses()->count()
                    ];
                });
            
            // Для обратной совместимости с примером ответа
            $categoryNames = $categories->pluck('name')->toArray();
            
            return response()->json([
                'categories' => $categoryNames,
                'categories_with_details' => $categories
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch categories',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 6.3 GET /courses/required/{positionId} - Обязательные курсы для должности
     */
    public function getRequiredCoursesForPosition($positionId)
    {
        try {
            $position = Position::findOrFail($positionId);
            
            $requiredCourses = PositionCourseRequirement::with('course')
                ->where('position_id', $positionId)
                ->get()
                ->map(function($requirement) {
                    return [
                        'courseId' => $requirement->course_id,
                        'name' => $requirement->course?->name ?? 'Неизвестный курс',
                        'isRequired' => $requirement->is_required,
                        'duration_hours' => $requirement->course?->duration_hours,
                        'periodicity_months' => $requirement->course?->periodicity_months
                    ];
                });
            
            // Все курсы для должности (включая необязательные)
            $allCourses = Course::all();
            $requiredCourseIds = $requiredCourses->pluck('courseId')->toArray();
            
            $optionalCourses = $allCourses->filter(function($course) use ($requiredCourseIds) {
                return !in_array($course->id, $requiredCourseIds);
            })->map(function($course) {
                return [
                    'courseId' => $course->id,
                    'name' => $course->name,
                    'isRequired' => false,
                    'duration_hours' => $course->duration_hours,
                    'periodicity_months' => $course->periodicity_months
                ];
            })->values();
            
            return response()->json([
                'positionId' => $position->id,
                'positionName' => $position->name,
                'requiredCourses' => $requiredCourses,
                'optionalCourses' => $optionalCourses,
                'totalRequired' => $requiredCourses->count(),
                'totalOptional' => $optionalCourses->count()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Position not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch required courses',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 6.4 POST /courses - Создание курса
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:courses,name',
                'category_id' => 'nullable|exists:course_categories,id',
                'category_name' => 'nullable|string|max:50',
                'duration_hours' => 'nullable|integer|min:1|max:1000',
                'periodicity_months' => 'nullable|integer|min:1|max:120',
                'description' => 'nullable|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Определяем категорию
            $categoryId = $request->category_id;
            if ($request->has('category_name') && !$categoryId) {
                $category = CourseCategory::firstOrCreate(
                    ['name' => $request->category_name],
                    ['name' => $request->category_name, 'sort_order' => 0]
                );
                $categoryId = $category->id;
            }
            
            $course = Course::create([
                'name' => $request->name,
                'category_id' => $categoryId,
                'duration_hours' => $request->duration_hours,
                'periodicity_months' => $request->periodicity_months,
                'description' => $request->description
            ]);
            
            $course->load('category');
            
            return response()->json([
                'id' => $course->id,
                'name' => $course->name,
                'category' => $course->category?->name ?? 'Без категории',
                'duration' => $course->duration_hours ? $course->duration_hours . ' часов' : 'Не указано',
                'periodicity' => $course->periodicity_months ? $course->periodicity_months . ' ' . $this->getPeriodicityText($course->periodicity_months) : 'Не указано',
                'description' => $course->description,
                'createdAt' => $course->created_at->toISOString()
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create course',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 6.5 PUT /courses/{id} - Обновление курса
     */
    public function update(Request $request, $id)
    {
        try {
            $course = Course::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:100|unique:courses,name,' . $id,
                'category_id' => 'nullable|exists:course_categories,id',
                'category_name' => 'nullable|string|max:50',
                'duration_hours' => 'nullable|integer|min:1|max:1000',
                'periodicity_months' => 'nullable|integer|min:1|max:120',
                'description' => 'nullable|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Обновляем название
            if ($request->has('name')) {
                $course->name = $request->name;
            }
            
            // Обновляем категорию
            if ($request->has('category_id')) {
                $course->category_id = $request->category_id;
            } elseif ($request->has('category_name')) {
                $category = CourseCategory::firstOrCreate(
                    ['name' => $request->category_name],
                    ['name' => $request->category_name, 'sort_order' => 0]
                );
                $course->category_id = $category->id;
            }
            
            if ($request->has('duration_hours')) {
                $course->duration_hours = $request->duration_hours;
            }
            
            if ($request->has('periodicity_months')) {
                $course->periodicity_months = $request->periodicity_months;
            }
            
            if ($request->has('description')) {
                $course->description = $request->description;
            }
            
            $course->save();
            $course->load('category');
            
            return response()->json([
                'id' => $course->id,
                'name' => $course->name,
                'category' => $course->category?->name ?? 'Без категории',
                'duration' => $course->duration_hours ? $course->duration_hours . ' часов' : 'Не указано',
                'periodicity' => $course->periodicity_months ? $course->periodicity_months . ' ' . $this->getPeriodicityText($course->periodicity_months) : 'Не указано',
                'updatedAt' => $course->updated_at->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update course',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 6.6 DELETE /courses/{id} - Удаление курса
     */
    public function destroy($id)
    {
        try {
            $course = Course::findOrFail($id);
            
            // Проверяем связанные записи
            $requirementsCount = PositionCourseRequirement::where('course_id', $id)->count();
            $brigadeRequirementsCount = BrigadeCourseRequirement::where('course_id', $id)->count();
            $employeeCoursesCount = $course->employeeCourses()->count();
            
            if ($requirementsCount > 0 || $brigadeRequirementsCount > 0 || $employeeCoursesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Невозможно удалить курс. Связанные записи: требования должностей ({$requirementsCount}), требования бригад ({$brigadeRequirementsCount}), назначения сотрудникам ({$employeeCoursesCount})"
                ], 400);
            }
            
            $course->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Курс удален'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Курс не найден'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении курса',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 6.7 GET /matrix/positions - Матрица компетенций (должности)
     */
    public function getCompetenceMatrix()
    {
        try {
            $positions = Position::with(['category'])->get();
            $courses = Course::with('category')->orderBy('category_id')->orderBy('name')->get();
            $brigades = Brigade::all();
            
            // Получаем требования по должностям
            $positionRequirements = PositionCourseRequirement::all();
            
            // Создаем структуру для хранения требований
            $requiredIdsByPosition = [];
            $optionalIdsByPosition = [];
            
            foreach ($positionRequirements as $requirement) {
                $positionId = $requirement->position_id;
                $courseId = $requirement->course_id;
                $isRequired = $requirement->is_required;
                
                if (!isset($requiredIdsByPosition[$positionId])) {
                    $requiredIdsByPosition[$positionId] = [];
                    $optionalIdsByPosition[$positionId] = [];
                }
                
                if ($isRequired) {
                    $requiredIdsByPosition[$positionId][] = $courseId;
                } else {
                    $optionalIdsByPosition[$positionId][] = $courseId;
                }
            }
            
            // Получаем требования по бригадам
            $brigadeRequirements = BrigadeCourseRequirement::all();
            $requiredIdsByBrigade = [];
            
            foreach ($brigadeRequirements as $requirement) {
                $brigadeId = $requirement->brigade_id;
                $courseId = $requirement->course_id;
                
                if (!isset($requiredIdsByBrigade[$brigadeId])) {
                    $requiredIdsByBrigade[$brigadeId] = [];
                }
                $requiredIdsByBrigade[$brigadeId][] = $courseId;
            }
            
            // Формируем матрицу для должностей
            $positionsMatrix = $positions->map(function($position) use ($courses, $requiredIdsByPosition, $optionalIdsByPosition) {
                $positionId = $position->id;
                $requiredIds = $requiredIdsByPosition[$positionId] ?? [];
                $optionalIds = $optionalIdsByPosition[$positionId] ?? [];
                
                $coursesData = $courses->map(function($course) use ($requiredIds, $optionalIds) {
                    $assigned = in_array($course->id, $requiredIds) || in_array($course->id, $optionalIds);
                    $isRequired = in_array($course->id, $requiredIds);
                    
                    return [
                        'courseId' => $course->id,
                        'name' => $course->name,
                        'category' => $course->category?->name,
                        'assigned' => $assigned,
                        'isRequired' => $isRequired
                    ];
                });
                
                return [
                    'id' => $position->id,
                    'name' => $position->name,
                    'category' => $position->category?->name ?? 'Без категории',
                    'courses' => $coursesData,
                    'requiredCount' => count($requiredIds),
                    'optionalCount' => count($optionalIds),
                    'totalCount' => $courses->count()
                ];
            });
            
            // Формируем матрицу для бригад
            $brigadesMatrix = $brigades->map(function($brigade) use ($courses, $requiredIdsByBrigade) {
                $requiredIds = $requiredIdsByBrigade[$brigade->id] ?? [];
                
                $coursesData = $courses->map(function($course) use ($requiredIds) {
                    return [
                        'courseId' => $course->id,
                        'name' => $course->name,
                        'category' => $course->category?->name,
                        'assigned' => in_array($course->id, $requiredIds)
                    ];
                });
                
                return [
                    'id' => $brigade->id,
                    'name' => $brigade->name,
                    'courses' => $coursesData,
                    'requiredCount' => count($requiredIds)
                ];
            });
            
            return response()->json([
                'positions' => $positionsMatrix,
                'brigades' => $brigadesMatrix,
                'courses' => $courses->map(function($course) {
                    return [
                        'id' => $course->id,
                        'name' => $course->name,
                        'category' => $course->category?->name,
                        'categoryId' => $course->category_id
                    ];
                }),
                'categories' => CourseCategory::orderBy('sort_order')->get(),
                'summary' => [
                    'totalPositions' => $positions->count(),
                    'totalBrigades' => $brigades->count(),
                    'totalCourses' => $courses->count()
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch competence matrix',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
        
    /**
     * 6.8 PUT /matrix/positions/{positionId}/courses/{courseId} - Назначить/отменить курс для должности
     */
    public function assignCourseToPosition(Request $request, $positionId, $courseId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'assigned' => 'required|boolean',
                'is_required' => 'nullable|boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $position = Position::findOrFail($positionId);
            $course = Course::findOrFail($courseId);
            $assigned = $request->assigned;
            $isRequired = $request->get('is_required', true);
            
            if ($assigned) {
                // Назначаем курс
                $requirement = PositionCourseRequirement::updateOrCreate(
                    [
                        'position_id' => $positionId,
                        'course_id' => $courseId
                    ],
                    [
                        'is_required' => $isRequired
                    ]
                );
                
                $message = "Курс назначен должности";
            } else {
                // Отменяем курс
                PositionCourseRequirement::where('position_id', $positionId)
                    ->where('course_id', $courseId)
                    ->delete();
                
                $message = "Курс отменен для должности";
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'positionId' => $position->id,
                'positionName' => $position->name,
                'courseId' => $course->id,
                'courseName' => $course->name,
                'assigned' => $assigned,
                'isRequired' => $assigned ? $isRequired : null
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Position or course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign course to position',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 6.9 PUT /matrix/brigades/{brigadeId}/courses/{courseId} - Назначить/отменить курс для бригады
     */
    public function assignCourseToBrigade(Request $request, $brigadeId, $courseId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'assigned' => 'required|boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $brigade = Brigade::findOrFail($brigadeId);
            $course = Course::findOrFail($courseId);
            $assigned = $request->assigned;
            
            if ($assigned) {
                // Назначаем курс
                $requirement = BrigadeCourseRequirement::updateOrCreate(
                    [
                        'brigade_id' => $brigadeId,
                        'course_id' => $courseId
                    ],
                    [
                        'is_required' => true
                    ]
                );
                
                $message = "Курс назначен бригаде";
            } else {
                // Отменяем курс
                BrigadeCourseRequirement::where('brigade_id', $brigadeId)
                    ->where('course_id', $courseId)
                    ->delete();
                
                $message = "Курс отменен для бригады";
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'brigadeId' => $brigade->id,
                'brigadeName' => $brigade->name,
                'courseId' => $course->id,
                'courseName' => $course->name,
                'assigned' => $assigned
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brigade or course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign course to brigade',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить детали курса
     */
    public function show($id)
    {
        try {
            $course = Course::with(['category'])->findOrFail($id);
            
            // Статистика по курсу
            $totalAssignments = $course->employeeCourses()->count();
            $completedAssignments = $course->employeeCourses()
                ->where('status', 'active')
                ->whereNotNull('completed_date')
                ->count();
            $expiredAssignments = $course->employeeCourses()
                ->where('status', 'expired')
                ->count();
            
            $positionsCount = $course->positionRequirements()->count();
            $brigadesCount = $course->brigadeRequirements()->count();
            
            return response()->json([
                'id' => $course->id,
                'name' => $course->name,
                'category' => $course->category?->name ?? 'Без категории',
                'category_id' => $course->category_id,
                'duration_hours' => $course->duration_hours,
                'periodicity_months' => $course->periodicity_months,
                'description' => $course->description,
                'statistics' => [
                    'total_assignments' => $totalAssignments,
                    'completed' => $completedAssignments,
                    'expired' => $expiredAssignments,
                    'completion_rate' => $totalAssignments > 0 
                        ? round(($completedAssignments / $totalAssignments) * 100)
                        : 0
                ],
                'requirements' => [
                    'positions' => $positionsCount,
                    'brigades' => $brigadesCount
                ],
                'created_at' => $course->created_at?->toISOString(),
                'updated_at' => $course->updated_at?->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch course details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Получить текст периодичности
     */
    private function getPeriodicityText($months)
    {
        if ($months == 12) {
            return 'год';
        } elseif ($months == 6) {
            return 'месяцев';
        } elseif ($months == 24) {
            return 'года';
        } else {
            return 'мес.';
        }
    }
}
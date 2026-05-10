<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee; 
use App\Models\EmployeeCourse; 
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
use App\Models\TrainingEvent;

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
            
            // Фильтр по подкатегории
            if ($request->has('subcategory')) {
                $query->where('subcategory', $request->subcategory);
            }
            
            // Фильтр по типу
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            
            // Фильтр по направлению
            if ($request->has('direction')) {
                $query->where('direction', $request->direction);
            }
            
            // Поиск по названию
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('subcategory', 'LIKE', "%{$search}%")
                    ->orWhere('direction', 'LIKE', "%{$search}%");
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
                    'subcategory' => $course->subcategory,      // Добавлено
                    'type' => $course->type,                    // Добавлено
                    'legal_basis' => $course->legal_basis,      // Добавлено
                    'direction' => $course->direction,          // Добавлено
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
            
            // Получаем уникальные подкатегории, типы и направления
            $subcategories = Course::whereNotNull('subcategory')
                ->distinct()
                ->pluck('subcategory')
                ->values();
            
            $types = Course::whereNotNull('type')
                ->distinct()
                ->pluck('type')
                ->values();
            
            $directions = Course::whereNotNull('direction')
                ->distinct()
                ->pluck('direction')
                ->values();
            
            // Для обратной совместимости с примером ответа
            $categoryNames = $categories->pluck('name')->toArray();
            
            return response()->json([
                'categories' => $categoryNames,
                'categories_with_details' => $categories,
                'filters' => [
                    'subcategories' => $subcategories,
                    'types' => $types,
                    'directions' => $directions
                ]
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
                        'subcategory' => $requirement->course?->subcategory,
                        'type' => $requirement->course?->type,
                        'direction' => $requirement->course?->direction,
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
                    'subcategory' => $course->subcategory,
                    'type' => $course->type,
                    'direction' => $course->direction,
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
                'subcategory' => 'nullable|string|max:100',      // Добавлено
                'type' => 'nullable|string|max:50',              // Добавлено
                'legal_basis' => 'nullable|string',              // Добавлено
                'direction' => 'nullable|string|max:100',        // Добавлено
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
                'subcategory' => $request->subcategory,      // Добавлено
                'type' => $request->type,                    // Добавлено
                'legal_basis' => $request->legal_basis,      // Добавлено
                'direction' => $request->direction,          // Добавлено
                'duration_hours' => $request->duration_hours,
                'periodicity_months' => $request->periodicity_months,
                'description' => $request->description
            ]);
            
            $course->load('category');
            
            return response()->json([
                'id' => $course->id,
                'name' => $course->name,
                'category' => $course->category?->name ?? 'Без категории',
                'subcategory' => $course->subcategory,      // Добавлено
                'type' => $course->type,                    // Добавлено
                'legal_basis' => $course->legal_basis,      // Добавлено
                'direction' => $course->direction,          // Добавлено
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
                'subcategory' => 'nullable|string|max:100',      // Добавлено
                'type' => 'nullable|string|max:50',              // Добавлено
                'legal_basis' => 'nullable|string',              // Добавлено
                'direction' => 'nullable|string|max:100',        // Добавлено
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
            
            // Обновляем новые поля
            if ($request->has('subcategory')) {
                $course->subcategory = $request->subcategory;
            }
            if ($request->has('type')) {
                $course->type = $request->type;
            }
            if ($request->has('legal_basis')) {
                $course->legal_basis = $request->legal_basis;
            }
            if ($request->has('direction')) {
                $course->direction = $request->direction;
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
                'subcategory' => $course->subcategory,      // Добавлено
                'type' => $course->type,                    // Добавлено
                'legal_basis' => $course->legal_basis,      // Добавлено
                'direction' => $course->direction,          // Добавлено
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
     * GET /courses/filters - Получить доступные фильтры для курсов
     */
    public function getFilters()
    {
        try {
            $subcategories = Course::whereNotNull('subcategory')
                ->distinct()
                ->pluck('subcategory')
                ->values();
            
            $types = Course::whereNotNull('type')
                ->distinct()
                ->pluck('type')
                ->values();
            
            $directions = Course::whereNotNull('direction')
                ->distinct()
                ->pluck('direction')
                ->values();
            
            return response()->json([
                'subcategories' => $subcategories,
                'types' => $types,
                'directions' => $directions
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch filters',
                'message' => $e->getMessage()
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
            $positionRequirements = PositionCourseRequirement::all()
                ->groupBy('position_id')
                ->map(function($items) {
                    $requiredIds = [];
                    $optionalIds = [];
                    foreach ($items as $item) {
                        if ($item->is_required) {
                            $requiredIds[] = $item->course_id;
                        } else {
                            $optionalIds[] = $item->course_id;
                        }
                    }
                    return [
                        'required' => $requiredIds,
                        'optional' => $optionalIds
                    ];
                });
            
            // Получаем требования по бригадам
            $brigadeRequirements = BrigadeCourseRequirement::all()
                ->groupBy('brigade_id')
                ->map(function($items) {
                    return $items->pluck('course_id')->toArray();
                });
            
            // Формируем матрицу для должностей
            $positionsMatrix = $positions->map(function($position) use ($courses, $positionRequirements) {
                $positionReqs = $positionRequirements[$position->id] ?? ['required' => [], 'optional' => []];
                $requiredIds = $positionReqs['required'];
                $optionalIds = $positionReqs['optional'];
                
                $coursesData = $courses->map(function($course) use ($requiredIds, $optionalIds) {
                    $assigned = in_array($course->id, $requiredIds) || in_array($course->id, $optionalIds);
                    $isRequired = in_array($course->id, $requiredIds);
                    
                    return [
                        'courseId' => $course->id,
                        'name' => $course->name,
                        'category' => $course->category?->name,
                        'subcategory' => $course->subcategory,
                        'type' => $course->type,
                        'direction' => $course->direction,
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
                    'totalCount' => $courses->count()
                ];
            });
            
            // Формируем матрицу для бригад
            $brigadesMatrix = $brigades->map(function($brigade) use ($courses, $brigadeRequirements) {
                $requiredIds = $brigadeRequirements[$brigade->id] ?? [];
                
                $coursesData = $courses->map(function($course) use ($requiredIds) {
                    return [
                        'courseId' => $course->id,
                        'name' => $course->name,
                        'category' => $course->category?->name,
                        'subcategory' => $course->subcategory,
                        'type' => $course->type,
                        'direction' => $course->direction,
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
                        'categoryId' => $course->category_id,
                        'subcategory' => $course->subcategory,
                        'type' => $course->type,
                        'direction' => $course->direction
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
                'message' => $e->getMessage()
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
            
            if ($assigned && $isRequired) {
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
                $this->autoAssignCourseToPositionEmployees($positionId, $courseId);
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

    private function autoAssignCourseToPositionEmployees($positionId, $courseId)
{
    try {
        $employees = Employee::where('position_id', $positionId)
            ->where('status', 'active')
            ->get();
        
        $course = Course::find($courseId);
        $assignedDate = now();
        $expirationDate = $course && $course->periodicity_months 
            ? $assignedDate->copy()->addMonths($course->periodicity_months)
            : null;
        
        foreach ($employees as $employee) {
            $existing = EmployeeCourse::where('employee_id', $employee->id)
                ->where('course_id', $courseId)
                ->first();
            
            if (!$existing) {
                EmployeeCourse::create([
                    'employee_id' => $employee->id,
                    'course_id' => $courseId,
                    'status' => 'required',
                    'assigned_date' => $assignedDate,
                    'expiration_date' => $expirationDate
                ]);
            }
        }
    } catch (\Exception $e) {
        \Log::error('Auto assign course error: ' . $e->getMessage());
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
                'subcategory' => $course->subcategory,      // Добавлено
                'type' => $course->type,                    // Добавлено
                'legal_basis' => $course->legal_basis,      // Добавлено
                'direction' => $course->direction,          // Добавлено
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

    /**
     * GET /courses/{courseId}/employees - Получить сотрудников, привязанных к курсу
     */
    public function getCourseEmployees($courseId)
    {
        try {
            $course = Course::with(['category'])->findOrFail($courseId);
            
            // Получаем всех сотрудников с этим курсом
            $employeeCourses = EmployeeCourse::with(['employee' => function($query) {
                    $query->with(['position', 'department', 'brigade']);
                }])
                ->where('course_id', $courseId)
                ->get();
            
            $employees = $employeeCourses->map(function($employeeCourse) {
                $employee = $employeeCourse->employee;
                
                // Формируем ФИО
                $fullName = trim(implode(' ', array_filter([
                    $employee->last_name,
                    $employee->first_name,
                    $employee->middle_name
                ])));
                
                if (empty($fullName)) {
                    $fullName = $employee->full_name;
                }
                
                return [
                    'id' => $employee->id,
                    'personnel_number' => $employee->personnel_number,
                    'full_name' => $fullName,
                    'last_name' => $employee->last_name,
                    'first_name' => $employee->first_name,
                    'middle_name' => $employee->middle_name,
                    'position' => $employee->position?->name ?? 'Не указана',
                    'position_id' => $employee->position_id,
                    'department' => $employee->department?->name ?? 'Не указано',
                    'department_id' => $employee->department_id,
                   /* 'brigade' => $employee->brigade?->name ?? 'Не указана',
                    'brigade_id' => $employee->brigade_id,
                    'status' => $employee->status,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'training' => [
                        'id' => $employeeCourse->id,
                        'assigned_date' => $employeeCourse->assigned_date?->format('Y-m-d'),
                        'completed_date' => $employeeCourse->completed_date?->format('Y-m-d'),
                        'expiration_date' => $employeeCourse->expiration_date?->format('Y-m-d'),
                        'status' => $employeeCourse->status,
                        'certificate_number' => $employeeCourse->certificate_number,
                        'regulatory_acts' => $employeeCourse->regulatory_acts
                    ]*/
                ];
            });
            
            // Статистика по курсу
            $statistics = [
                'total_employees' => $employees->count(),
                'by_status' => [
                    'active' => $employees->where('training.status', 'active')->count(),
                    'expired' => $employees->where('training.status', 'expired')->count(),
                    'expiring' => $employees->where('training.status', 'expiring')->count(),
                    'required' => $employees->where('training.status', 'required')->count(),
                    'no_data' => $employees->where('training.status', 'noData')->count()
                ],
                'by_department' => $employees->groupBy('department_id')->map(function($group, $deptId) {
                    $dept = $group->first()['department'] ?? 'Без подразделения';
                    return [
                        'department_name' => $dept,
                        'count' => $group->count()
                    ];
                })->values(),
                'compliance_rate' => $employees->count() > 0 
                    ? round(($employees->where('training.status', 'active')->count() / $employees->count()) * 100)
                    : 0
            ];
            
            return response()->json([
                'course' => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'category' => $course->category?->name,
                    'duration_hours' => $course->duration_hours,
                    'periodicity_months' => $course->periodicity_months
                ],
                'employees' => $employees,
                'statistics' => $statistics,
                'total' => $employees->count()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch course employees',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /courses/{courseId}/events - Получить все мероприятия для курса
     */
    public function getCourseEvents($courseId)
    {
        try {
            $course = Course::with(['category'])->findOrFail($courseId);
            
            $events = TrainingEvent::with(['course', 'participants'])
                ->where('course_id', $courseId)
                ->orderBy('start_date', 'desc')
                ->get();
            
            $formattedEvents = $events->map(function($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'format' => $event->format,
                    'format_label' => $this->getFormatLabel($event->format),
                    'start_date' => $event->start_date->format('Y-m-d'),
                    'end_date' => $event->end_date?->format('Y-m-d'),
                    'location' => $event->location,
                    'training_center' => $event->training_center,
                    'status' => $event->status,
                    'status_label' => $this->getStatusLabel($event->status),
                    'status_color' => $this->getStatusColor($event->status),
                    'cost' => $event->cost,
                    'participants_count' => $event->participants->count(),
                    'max_participants' => $event->max_participants,
                    'available_slots' => $event->max_participants 
                        ? max(0, $event->max_participants - $event->participants->count())
                        : null
                ];
            });
            
            // Статистика по мероприятиям курса
            $statistics = [
                'total_events' => $events->count(),
                'by_status' => [
                    'draft' => $events->where('status', 'draft')->count(),
                    'confirmed' => $events->where('status', 'confirmed')->count(),
                    'in_progress' => $events->where('status', 'in_progress')->count(),
                    'completed' => $events->where('status', 'completed')->count(),
                    'cancelled' => $events->where('status', 'cancelled')->count()
                ],
                'by_format' => [
                    'onsite' => $events->where('format', 'onsite')->count(),
                    'online' => $events->where('format', 'online')->count(),
                    'hybrid' => $events->where('format', 'hybrid')->count()
                ],
                'total_participants' => $events->sum(fn($e) => $e->participants->count()),
                'upcoming_events' => $events->where('start_date', '>=', now())->count(),
                'past_events' => $events->where('start_date', '<', now())->count()
            ];
            
            return response()->json([
                'course' => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'category' => $course->category?->name
                ],
                'events' => $formattedEvents,
                'statistics' => $statistics,
                'total' => $events->count()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Get course events error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch course events',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Вспомогательные методы (если их нет)
    private function getFormatLabel($format)
    {
        return [
            'onsite' => 'Очное',
            'online' => 'Онлайн',
            'hybrid' => 'Гибридное'
        ][$format] ?? $format;
    }

    private function getStatusLabel($status)
    {
        return [
            'draft' => 'Черновик',
            'confirmed' => 'Подтверждено',
            'in_progress' => 'В процессе',
            'completed' => 'Завершено',
            'cancelled' => 'Отменено'
        ][$status] ?? $status;
    }

    private function getStatusColor($status)
    {
        return [
            'draft' => '#9ca3af',
            'confirmed' => '#10b981',
            'in_progress' => '#3b82f6',
            'completed' => '#8b5cf6',
            'cancelled' => '#ef4444'
        ][$status] ?? '#9ca3af';
    }
}
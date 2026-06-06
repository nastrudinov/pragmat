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
use Illuminate\Support\Facades\Cache;

class CourseController extends Controller
{
    /**
     * 6.1 GET /courses - Список курсов
     */
  public function index(Request $request)
    {
        try {
            $whereConditions = [];
            $bindings = [];
            
            // Временно убрали фильтр is_show
            // $whereConditions[] = "c.is_show = 1";
            
            if ($request->has('category_id') && $request->category_id) {
                $whereConditions[] = "c.category_id = ?";
                $bindings[] = $request->category_id;
            }
            
            if ($request->has('subcategory') && $request->subcategory) {
                $whereConditions[] = "c.subcategory = ?";
                $bindings[] = $request->subcategory;
            }
            
            if ($request->has('type') && $request->type) {
                $whereConditions[] = "c.type = ?";
                $bindings[] = $request->type;
            }
            
            if ($request->has('direction') && $request->direction) {
                $whereConditions[] = "c.direction = ?";
                $bindings[] = $request->direction;
            }
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $whereConditions[] = "(c.name LIKE ? OR c.subcategory LIKE ? OR c.direction LIKE ?)";
                $bindings[] = "%{$search}%";
                $bindings[] = "%{$search}%";
                $bindings[] = "%{$search}%";
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $sortField = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            $allowedSortFields = ['name', 'category_id', 'subcategory', 'type', 'direction', 'duration_hours', 'periodicity_months', 'created_at'];
            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'name';
            }
            
            $sortDirection = strtolower($sortDirection) === 'desc' ? 'DESC' : 'ASC';
            
            $sql = "
                SELECT 
                    c.id,
                    c.name,
                    c.category_id,
                    c.subcategory,
                    c.type,
                    c.legal_basis,
                    c.direction,
                    c.duration_hours,
                    c.periodicity_months,
                    c.is_show,
                    c.description,
                    c.created_at,
                    cc.name as category_name
                FROM courses c
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                {$whereClause}
                ORDER BY c.{$sortField} {$sortDirection}
            ";
            
            $courses = DB::select($sql, $bindings);
            
            $formattedCourses = array_map(function($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'category' => $course->category_name ?? 'Без категории',
                    'category_id' => $course->category_id,
                    'subcategory' => $course->subcategory,
                    'type' => $course->type,
                    'legal_basis' => $course->legal_basis,
                    'direction' => $course->direction,
                    'duration' => $course->duration_hours ? $course->duration_hours . ' часов' : 'Не указано',
                    'duration_hours' => $course->duration_hours,
                    'periodicity_months' => $course->periodicity_months,
                    'is_show' => (bool)$course->is_show,
                    'description' => $course->description,
                    'created_at' => $course->created_at
                ];
            }, $courses);
            
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

    private function getPeriodicityText(int $months): string
    {
        $years = floor($months / 12);
        $remainingMonths = $months % 12;
        
        if ($years > 0 && $remainingMonths > 0) {
            return $years . ' ' . $this->getYearText($years) . ' ' . $remainingMonths . ' ' . $this->getMonthText($remainingMonths);
        } elseif ($years > 0) {
            return $years . ' ' . $this->getYearText($years);
        } else {
            return $months . ' ' . $this->getMonthText($months);
        }
    }

    private function getYearText(int $years): string
    {
        $lastDigit = $years % 10;
        $lastTwoDigits = $years % 100;
        
        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'лет';
        }
        
        if ($lastDigit == 1) {
            return 'год';
        }
        
        if ($lastDigit >= 2 && $lastDigit <= 4) {
            return 'года';
        }
        
        return 'лет';
    }

    private function getMonthText(int $months): string
    {
        $lastDigit = $months % 10;
        $lastTwoDigits = $months % 100;
        
        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'месяцев';
        }
        
        if ($lastDigit == 1) {
            return 'месяц';
        }
        
        if ($lastDigit >= 2 && $lastDigit <= 4) {
            return 'месяца';
        }
        
        return 'месяцев';
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
                'is_show' => 'nullable|boolean',
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
                'is_show' => $request->get('is_show', false),
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
                'is_show' => (bool)$course->is_show,
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
                'is_show' => 'nullable|boolean',
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
            if ($request->has('is_show')) {
                $course->is_show = $request->is_show;
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
                'is_show' => (bool)$course->is_show,
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
     * GET /courses/popular - Топ-5 курсов со статистикой
     */
    public function getPopularCourses()
    {
        try {
            $sql = "
                SELECT 
                    c.id,
                    c.name,
                    c.category_id,
                    cc.name as category_name,
                    COUNT(DISTINCT ec.employee_id) as total_employees,
                    SUM(CASE 
                        WHEN ec.expiration_date IS NOT NULL 
                        AND ec.expiration_date < CURDATE() 
                        THEN 1 ELSE 0 END) as expired_count,
                    SUM(CASE 
                        WHEN ec.status = 'required' 
                        OR (ec.completed_date IS NULL AND ec.expiration_date IS NULL)
                        THEN 1 ELSE 0 END) as required_count,
                    SUM(CASE 
                        WHEN ec.expiration_date IS NOT NULL 
                        AND ec.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                        THEN 1 ELSE 0 END) as expiring_month_count,
                    SUM(CASE 
                        WHEN ec.expiration_date IS NOT NULL 
                        AND ec.expiration_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 31 DAY) AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) 
                        THEN 1 ELSE 0 END) as expiring_two_months_count
                FROM courses c
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                LEFT JOIN employee_courses ec ON c.id = ec.course_id
                WHERE c.is_show = 1
                GROUP BY c.id, c.name, c.category_id, cc.name
                ORDER BY total_employees DESC
                LIMIT 5
            ";
            
            $courses = DB::select($sql);
            
            $result = array_map(function($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'category' => $course->category_name ?? 'Без категории',
                    'category_id' => $course->category_id,
                    'statistics' => [
                        'total_employees' => (int)$course->total_employees,
                        'expired' => (int)$course->expired_count,
                        'required' => (int)$course->required_count,
                        'expiring_in_month' => (int)$course->expiring_month_count,
                        'expiring_in_two_months' => (int)$course->expiring_two_months_count
                    ]
                ];
            }, $courses);
            
            return response()->json([
                'courses' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch popular courses',
                'message' => $e->getMessage()
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
        // Один запрос для получения всей матрицы должностей
        $positionsMatrixRaw = DB::select("
            SELECT 
                p.id as position_id,
                p.name as position_name,
                pc.name as position_category,
                c.id as course_id,
                c.name as course_name,
                cc.name as course_category,
                c.subcategory,
                c.type,
                c.direction,
                pcr.is_required,
                CASE WHEN pcr.id IS NOT NULL THEN 1 ELSE 0 END as assigned
            FROM positions p
            LEFT JOIN course_categories pc ON p.category_id = pc.id
            CROSS JOIN courses c
            LEFT JOIN course_categories cc ON c.category_id = cc.id
            LEFT JOIN position_course_requirements pcr ON pcr.position_id = p.id AND pcr.course_id = c.id
            WHERE pcr.id IS NOT NULL OR pcr.is_required = 1
            ORDER BY p.id, c.category_id, c.name
        ");
        
        // Группируем результат по должностям
        $positionsMatrix = collect();
        $currentPosition = null;
        $positionData = [];
        
        foreach ($positionsMatrixRaw as $row) {
            if ($currentPosition !== $row->position_id) {
                if ($currentPosition !== null) {
                    $positionsMatrix->push($positionData);
                }
                $currentPosition = $row->position_id;
                $positionData = [
                    'id' => $row->position_id,
                    'name' => $row->position_name,
                    'category' => $row->position_category ?? 'Без категории',
                    'courses' => [],
                    'requiredCount' => 0,
                    'totalCount' => 0
                ];
            }
            
            $isRequired = (bool)$row->is_required;
            if ($isRequired) {
                $positionData['requiredCount']++;
            }
            
            $positionData['courses'][] = [
                'courseId' => $row->course_id,
                'name' => $row->course_name,
                'category' => $row->course_category,
                'subcategory' => $row->subcategory,
                'type' => $row->type,
                'direction' => $row->direction,
                'assigned' => (bool)$row->assigned,
                'isRequired' => $isRequired
            ];
            $positionData['totalCount']++;
        }
        
        if ($currentPosition !== null) {
            $positionsMatrix->push($positionData);
        }
        
        // Запрос для бригад
        $brigadesMatrixRaw = DB::select("
            SELECT 
                b.id as brigade_id,
                b.name as brigade_name,
                c.id as course_id,
                c.name as course_name,
                cc.name as course_category,
                c.subcategory,
                c.type,
                c.direction
            FROM brigades b
            CROSS JOIN courses c
            LEFT JOIN course_categories cc ON c.category_id = cc.id
            INNER JOIN brigade_course_requirements bcr ON bcr.brigade_id = b.id AND bcr.course_id = c.id
            ORDER BY b.id, c.category_id, c.name
        ");
        
        $brigadesMatrix = collect();
        $currentBrigade = null;
        $brigadeData = [];
        
        foreach ($brigadesMatrixRaw as $row) {
            if ($currentBrigade !== $row->brigade_id) {
                if ($currentBrigade !== null) {
                    $brigadesMatrix->push($brigadeData);
                }
                $currentBrigade = $row->brigade_id;
                $brigadeData = [
                    'id' => $row->brigade_id,
                    'name' => $row->brigade_name,
                    'courses' => [],
                    'requiredCount' => 0
                ];
            }
            
            $brigadeData['courses'][] = [
                'courseId' => $row->course_id,
                'name' => $row->course_name,
                'category' => $row->course_category,
                'subcategory' => $row->subcategory,
                'type' => $row->type,
                'direction' => $row->direction,
                'assigned' => true
            ];
            $brigadeData['requiredCount']++;
        }
        
        if ($currentBrigade !== null) {
            $brigadesMatrix->push($brigadeData);
        }
        
        // Получаем курсы и категории
        $courses = DB::table('courses')
            ->leftJoin('course_categories', 'courses.category_id', '=', 'course_categories.id')
            ->select('courses.*', 'course_categories.name as category_name')
            ->orderBy('courses.category_id')
            ->orderBy('courses.name')
            ->get();
        
        $categories = DB::table('course_categories')
            ->orderBy('sort_order')
            ->get();
        
        return response()->json([
            'positions' => $positionsMatrix,
            'brigades' => $brigadesMatrix,
            'courses' => $courses,
            'categories' => $categories,
            'summary' => [
                'totalPositions' => DB::table('positions')->count(),
                'totalBrigades' => DB::table('brigades')->count(),
                'totalCourses' => DB::table('courses')->count()
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
                
                // Проверка на случай отсутствия сотрудника
                if (!$employee) {
                    return null;
                }
                
                // Формируем ФИО
                $fullName = trim(implode(' ', array_filter([
                    $employee->last_name ?? '',
                    $employee->first_name ?? '',
                    $employee->middle_name ?? ''
                ])));
                
                if (empty($fullName)) {
                    $fullName = $employee->full_name ?? 'Неизвестный сотрудник';
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
                    'training_status' => $employeeCourse->status,
                    'expiration_date' => $employeeCourse->expiration_date?->format('Y-m-d'),
                    'assigned_date' => $employeeCourse->assigned_date?->format('Y-m-d'),
                    'completed_date' => $employeeCourse->completed_date?->format('Y-m-d')
                ];
            })->filter(); // Удаляем null значения
            
            // Статистика по курсу (исправлено)
            $statistics = [
                'total_employees' => $employees->count(),
                'by_status' => [
                    'active' => $employees->where('training_status', 'active')->count(),
                    'expired' => $employees->where('training_status', 'expired')->count(),
                    'expiring' => $employees->where('training_status', 'expiring')->count(),
                    'required' => $employees->where('training_status', 'required')->count(),
                    'no_data' => $employees->where('training_status', 'noData')->count()
                ],
                'by_department' => $employees->groupBy('department_id')->map(function($group, $deptId) {
                    $dept = $group->first()['department'] ?? 'Без подразделения';
                    return [
                        'department_name' => $dept,
                        'count' => $group->count()
                    ];
                })->values(),
                'compliance_rate' => $employees->count() > 0 
                    ? round(($employees->where('training_status', 'active')->count() / $employees->count()) * 100)
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
                'employees' => $employees->values(),
                'statistics' => $statistics,
                'total' => $employees->count()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Get course employees error: ' . $e->getMessage());
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
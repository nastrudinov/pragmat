<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'personnel_number',  // Добавлено
        'full_name', 
        'last_name', 
        'first_name', 
        'middle_name',
        'position_id', 
        'brigade_id', 
        'department_id', 
        'email', 
        'phone', 
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function brigade(): BelongsTo
    {
        return $this->belongsTo(Brigade::class);
    }

    // Добавляем отношение к подразделению
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function userAccount(): HasOne
    {
        return $this->hasOne(UserAccount::class);
    }

    public function employeeCourses(): HasMany
    {
        return $this->hasMany(EmployeeCourse::class);
    }

    public function mobilizations(): HasMany
    {
        return $this->hasMany(MobilizationEmployee::class);
    }

    // Подразделения, где сотрудник является руководителем
    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_employee_id');
    }

     public function assignRequiredCoursesFromMatrix(): void
    {
        // Получаем ID курсов из матрицы должности
        $positionCourseIds = PositionCourseRequirement::where('position_id', $this->position_id)
            ->pluck('course_id')
            ->toArray();
        
        // Получаем ID курсов из матрицы бригады (если есть бригада)
        $brigadeCourseIds = [];
        if ($this->brigade_id) {
            $brigadeCourseIds = BrigadeCourseRequirement::where('brigade_id', $this->brigade_id)
                ->pluck('course_id')
                ->toArray();
        }
        
        // Объединяем и убираем дубликаты
        $requiredCourseIds = array_unique(array_merge($positionCourseIds, $brigadeCourseIds));
        
        if (empty($requiredCourseIds)) {
            return; // Нет обязательных курсов
        }
        
        $now = Carbon::now()->toDateString();
        
        // Подготавливаем данные для вставки
        $assignments = [];
        foreach ($requiredCourseIds as $courseId) {
            // Проверяем, не назначен ли уже этот курс сотруднику
            $exists = EmployeeCourse::where('employee_id', $this->id)
                ->where('course_id', $courseId)
                ->exists();
            
            if (!$exists) {
                $assignments[] = [
                    'employee_id' => $this->id,
                    'course_id' => $courseId,
                    'status' => 'required', // или 'active' - решать вам
                    'assigned_date' => $now,
                    'completed_date' => null,
                    'expiration_date' => null, // КЛЮЧЕВОЕ - без даты истечения
                    'certificate_number' => null,
                    'regulatory_acts' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        // Массовая вставка
        if (!empty($assignments)) {
            EmployeeCourse::insert($assignments);
            
            // Очищаем кэш для этого сотрудника
            $this->clearEmployeeCache();
        }
    }
    
    /**
     * Очистка кэша связанного с сотрудником
     */
    protected function clearEmployeeCache(): void
    {
        cache()->forget("employee_{$this->id}_trainings");
        cache()->forget("heatmap_employee_{$this->id}");
    }
}
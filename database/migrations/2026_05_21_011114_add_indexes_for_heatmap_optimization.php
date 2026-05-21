<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Индексы для таблицы employees
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasIndex('employees', 'idx_employees_brigade_id')) {
                $table->index('brigade_id', 'idx_employees_brigade_id');
            }
            if (!Schema::hasIndex('employees', 'idx_employees_department_id')) {
                $table->index('department_id', 'idx_employees_department_id');
            }
            if (!Schema::hasIndex('employees', 'idx_employees_position_id')) {
                $table->index('position_id', 'idx_employees_position_id');
            }
            if (!Schema::hasIndex('employees', 'idx_employees_status')) {
                $table->index('status', 'idx_employees_status');
            }
            if (!Schema::hasIndex('employees', 'idx_employees_full_name')) {
                $table->index('full_name', 'idx_employees_full_name');
            }
        });

        // Индексы для таблицы employee_courses
        Schema::table('employee_courses', function (Blueprint $table) {
            if (!Schema::hasIndex('employee_courses', 'idx_employee_courses_employee_course')) {
                $table->index(['employee_id', 'course_id'], 'idx_employee_courses_employee_course');
            }
            if (!Schema::hasIndex('employee_courses', 'idx_employee_courses_status')) {
                $table->index('status', 'idx_employee_courses_status');
            }
            if (!Schema::hasIndex('employee_courses', 'idx_employee_courses_expiration_date')) {
                $table->index('expiration_date', 'idx_employee_courses_expiration_date');
            }
            if (!Schema::hasIndex('employee_courses', 'idx_employee_courses_employee_id')) {
                $table->index('employee_id', 'idx_employee_courses_employee_id');
            }
            if (!Schema::hasIndex('employee_courses', 'idx_employee_courses_course_id')) {
                $table->index('course_id', 'idx_employee_courses_course_id');
            }
        });

        // Индексы для таблицы courses
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasIndex('courses', 'idx_courses_category_id')) {
                $table->index('category_id', 'idx_courses_category_id');
            }
            if (!Schema::hasIndex('courses', 'idx_courses_name')) {
                $table->index('name', 'idx_courses_name');
            }
        });

        // Индексы для таблицы positions
        Schema::table('positions', function (Blueprint $table) {
            if (!Schema::hasIndex('positions', 'idx_positions_name')) {
                $table->index('name', 'idx_positions_name');
            }
            if (!Schema::hasIndex('positions', 'idx_positions_category_id')) {
                $table->index('category_id', 'idx_positions_category_id');
            }
        });

        // Индексы для таблицы brigades
        Schema::table('brigades', function (Blueprint $table) {
            if (!Schema::hasIndex('brigades', 'idx_brigades_name')) {
                $table->index('name', 'idx_brigades_name');
            }
        });

        // Индексы для таблицы departments
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasIndex('departments', 'idx_departments_name')) {
                $table->index('name', 'idx_departments_name');
            }
            if (!Schema::hasIndex('departments', 'idx_departments_parent_id')) {
                $table->index('parent_id', 'idx_departments_parent_id');
            }
        });
    }

    public function down(): void
    {
        // Удаляем индексы из таблицы employees
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_employees_brigade_id');
            $table->dropIndexIfExists('idx_employees_department_id');
            $table->dropIndexIfExists('idx_employees_position_id');
            $table->dropIndexIfExists('idx_employees_status');
            $table->dropIndexIfExists('idx_employees_full_name');
        });

        // Удаляем индексы из таблицы employee_courses
        Schema::table('employee_courses', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_employee_courses_employee_course');
            $table->dropIndexIfExists('idx_employee_courses_status');
            $table->dropIndexIfExists('idx_employee_courses_expiration_date');
            $table->dropIndexIfExists('idx_employee_courses_employee_id');
            $table->dropIndexIfExists('idx_employee_courses_course_id');
        });

        // Удаляем индексы из таблицы courses
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_courses_category_id');
            $table->dropIndexIfExists('idx_courses_name');
        });

        // Удаляем индексы из таблицы positions
        Schema::table('positions', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_positions_name');
            $table->dropIndexIfExists('idx_positions_category_id');
        });

        // Удаляем индексы из таблицы brigades
        Schema::table('brigades', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_brigades_name');
        });

        // Удаляем индексы из таблицы departments
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_departments_name');
            $table->dropIndexIfExists('idx_departments_parent_id');
        });
    }
};
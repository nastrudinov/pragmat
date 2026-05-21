<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Проверяем существование таблицы перед добавлением индексов
        if (Schema::hasTable('employee_courses')) {
            Schema::table('employee_courses', function (Blueprint $table) {
                // Основные индексы для фильтрации
                $table->index('employee_id', 'idx_ec_employee_id');
                $table->index('course_id', 'idx_ec_course_id');
                $table->index('status', 'idx_ec_status');
                $table->index('expiration_date', 'idx_ec_expiration_date');
                $table->index('assigned_date', 'idx_ec_assigned_date');
                $table->index('completed_date', 'idx_ec_completed_date');
                
                // Составные индексы для ускорения сложных запросов
                $table->index(['employee_id', 'course_id'], 'idx_ec_employee_course');
                $table->index(['employee_id', 'expiration_date'], 'idx_ec_employee_expiration');
                $table->index(['course_id', 'expiration_date'], 'idx_ec_course_expiration');
                $table->index(['expiration_date', 'status'], 'idx_ec_expiration_status');
                $table->index(['status', 'expiration_date'], 'idx_ec_status_expiration');
                
                // Индекс для FULLTEXT поиска (если нужно)
                // $table->fullText('certificate_number', 'idx_ec_certificate_fulltext');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employee_courses')) {
            Schema::table('employee_courses', function (Blueprint $table) {
                // Удаляем индексы
                $table->dropIndex('idx_ec_employee_id');
                $table->dropIndex('idx_ec_course_id');
                $table->dropIndex('idx_ec_status');
                $table->dropIndex('idx_ec_expiration_date');
                $table->dropIndex('idx_ec_assigned_date');
                $table->dropIndex('idx_ec_completed_date');
                $table->dropIndex('idx_ec_employee_course');
                $table->dropIndex('idx_ec_employee_expiration');
                $table->dropIndex('idx_ec_course_expiration');
                $table->dropIndex('idx_ec_expiration_status');
                $table->dropIndex('idx_ec_status_expiration');
                // $table->dropIndex('idx_ec_certificate_fulltext');
            });
        }
    }
};
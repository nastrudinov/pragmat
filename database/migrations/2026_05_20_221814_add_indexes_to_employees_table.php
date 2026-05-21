<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                // Основные индексы для фильтрации
                $table->index('personnel_number', 'idx_emp_personnel_number');
                $table->index('status', 'idx_emp_status');
                $table->index('position_id', 'idx_emp_position_id');
                $table->index('department_id', 'idx_emp_department_id');
                $table->index('brigade_id', 'idx_emp_brigade_id');
                $table->index('created_at', 'idx_emp_created_at');
                
                // Составные индексы для ускорения фильтрации
                $table->index(['status', 'position_id'], 'idx_emp_status_position');
                $table->index(['department_id', 'status'], 'idx_emp_department_status');
                $table->index(['brigade_id', 'status'], 'idx_emp_brigade_status');
                
                // Индексы для поиска
                $table->index('full_name', 'idx_emp_full_name');
                $table->index('last_name', 'idx_emp_last_name');
                $table->index('first_name', 'idx_emp_first_name');
                $table->index('email', 'idx_emp_email');
                
                // Составной индекс для поиска по ФИО
                $table->index(['last_name', 'first_name', 'middle_name'], 'idx_emp_full_name_search');
            });
        }
    }
    
    public function down(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('idx_emp_personnel_number');
                $table->dropIndex('idx_emp_status');
                $table->dropIndex('idx_emp_position_id');
                $table->dropIndex('idx_emp_department_id');
                $table->dropIndex('idx_emp_brigade_id');
                $table->dropIndex('idx_emp_created_at');
                $table->dropIndex('idx_emp_status_position');
                $table->dropIndex('idx_emp_department_status');
                $table->dropIndex('idx_emp_brigade_status');
                $table->dropIndex('idx_emp_full_name');
                $table->dropIndex('idx_emp_last_name');
                $table->dropIndex('idx_emp_first_name');
                $table->dropIndex('idx_emp_email');
                $table->dropIndex('idx_emp_full_name_search');
            });
        }
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('courses')) {
            return;
        }
        
        // Получаем список существующих индексов
        $existingIndexes = $this->getExistingIndexes('courses');
        
        Schema::table('courses', function (Blueprint $table) use ($existingIndexes) {
            // Основные индексы для фильтрации
            if (!in_array('idx_courses_name', $existingIndexes)) {
                $table->index('name', 'idx_courses_name');
            }
            
            if (!in_array('idx_courses_category_id', $existingIndexes)) {
                $table->index('category_id', 'idx_courses_category_id');
            }
            
            if (!in_array('idx_courses_subcategory', $existingIndexes)) {
                $table->index('subcategory', 'idx_courses_subcategory');
            }
            
            if (!in_array('idx_courses_type', $existingIndexes)) {
                $table->index('type', 'idx_courses_type');
            }
            
            if (!in_array('idx_courses_direction', $existingIndexes)) {
                $table->index('direction', 'idx_courses_direction');
            }
            
            if (!in_array('idx_courses_duration_hours', $existingIndexes)) {
                $table->index('duration_hours', 'idx_courses_duration_hours');
            }
            
            if (!in_array('idx_courses_periodicity_months', $existingIndexes)) {
                $table->index('periodicity_months', 'idx_courses_periodicity_months');
            }
            
            if (!in_array('idx_courses_created_at', $existingIndexes)) {
                $table->index('created_at', 'idx_courses_created_at');
            }
            
            // Составные индексы для оптимизации частых запросов
            if (!in_array('idx_courses_category_subcategory', $existingIndexes)) {
                $table->index(['category_id', 'subcategory'], 'idx_courses_category_subcategory');
            }
            
            if (!in_array('idx_courses_type_direction', $existingIndexes)) {
                $table->index(['type', 'direction'], 'idx_courses_type_direction');
            }
            
            if (!in_array('idx_courses_category_type', $existingIndexes)) {
                $table->index(['category_id', 'type'], 'idx_courses_category_type');
            }
        });
    }
    
    private function getExistingIndexes(string $table): array
    {
        $database = DB::getDatabaseName();
        $indexes = DB::select("
            SELECT DISTINCT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ", [$database, $table]);
        
        return array_column($indexes, 'INDEX_NAME');
    }
    
    public function down(): void
    {
        if (!Schema::hasTable('courses')) {
            return;
        }
        
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_courses_name');
            $table->dropIndex('idx_courses_category_id');
            $table->dropIndex('idx_courses_subcategory');
            $table->dropIndex('idx_courses_type');
            $table->dropIndex('idx_courses_direction');
            $table->dropIndex('idx_courses_duration_hours');
            $table->dropIndex('idx_courses_periodicity_months');
            $table->dropIndex('idx_courses_created_at');
            $table->dropIndex('idx_courses_category_subcategory');
            $table->dropIndex('idx_courses_type_direction');
            $table->dropIndex('idx_courses_category_type');
        });
    }
};
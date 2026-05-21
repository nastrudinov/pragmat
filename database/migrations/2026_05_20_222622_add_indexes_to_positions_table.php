<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('positions')) {
            return;
        }
        
        // Получаем список существующих индексов
        $existingIndexes = $this->getExistingIndexes('positions');
        
        Schema::table('positions', function (Blueprint $table) use ($existingIndexes) {
            // Добавляем индексы только если их еще нет
            if (!in_array('idx_positions_name', $existingIndexes)) {
                $table->index('name', 'idx_positions_name');
            }
            
            if (!in_array('idx_positions_category_id', $existingIndexes)) {
                $table->index('category_id', 'idx_positions_category_id');
            }
            
            if (!in_array('idx_positions_created_at', $existingIndexes)) {
                $table->index('created_at', 'idx_positions_created_at');
            }
            
            if (!in_array('idx_positions_updated_at', $existingIndexes)) {
                $table->index('updated_at', 'idx_positions_updated_at');
            }
            
            if (!in_array('idx_positions_category_name', $existingIndexes)) {
                $table->index(['category_id', 'name'], 'idx_positions_category_name');
            }
        });
    }
    
    /**
     * Get existing indexes for a table.
     */
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
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('positions')) {
            return;
        }
        
        // Получаем список существующих индексов
        $existingIndexes = $this->getExistingIndexes('positions');
        
        Schema::table('positions', function (Blueprint $table) use ($existingIndexes) {
            // Удаляем индексы только если они существуют
            if (in_array('idx_positions_name', $existingIndexes)) {
                $table->dropIndex('idx_positions_name');
            }
            
            if (in_array('idx_positions_category_id', $existingIndexes)) {
                $table->dropIndex('idx_positions_category_id');
            }
            
            if (in_array('idx_positions_created_at', $existingIndexes)) {
                $table->dropIndex('idx_positions_created_at');
            }
            
            if (in_array('idx_positions_updated_at', $existingIndexes)) {
                $table->dropIndex('idx_positions_updated_at');
            }
            
            if (in_array('idx_positions_category_name', $existingIndexes)) {
                $table->dropIndex('idx_positions_category_name');
            }
        });
    }
};
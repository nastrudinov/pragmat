<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Индексы для таблицы courses
        Schema::table('courses', function (Blueprint $table) {
            $table->index('category_id', 'idx_courses_category_id');
            $table->index('name', 'idx_courses_name');
            $table->index(['category_id', 'name'], 'idx_courses_category_name');
            $table->index('subcategory', 'idx_courses_subcategory');
            $table->index('type', 'idx_courses_type');
            $table->index('direction', 'idx_courses_direction');
        });

        // 2. Индексы для таблицы position_course_requirements
        Schema::table('position_course_requirements', function (Blueprint $table) {
            $table->index('position_id', 'idx_pcr_position_id');
            $table->index('course_id', 'idx_pcr_course_id');
            $table->index('is_required', 'idx_pcr_is_required');
            $table->index(['position_id', 'course_id'], 'idx_pcr_position_course');
            $table->index(['position_id', 'is_required'], 'idx_pcr_position_required');
        });

        // 3. Индексы для таблицы brigade_course_requirements
        Schema::table('brigade_course_requirements', function (Blueprint $table) {
            $table->index('brigade_id', 'idx_bcr_brigade_id');
            $table->index('course_id', 'idx_bcr_course_id');
            $table->index(['brigade_id', 'course_id'], 'idx_bcr_brigade_course');
        });

        // 4. Индексы для таблицы positions
        Schema::table('positions', function (Blueprint $table) {
            $table->index('category_id', 'idx_positions_category_id');
            $table->index('name', 'idx_positions_name');
        });

        // 5. Индексы для таблицы brigades
        Schema::table('brigades', function (Blueprint $table) {
            $table->index('name', 'idx_brigades_name');
        });

        // 6. Индексы для таблицы course_categories
        Schema::table('course_categories', function (Blueprint $table) {
            $table->index('sort_order', 'idx_categories_sort_order');
            $table->index('name', 'idx_categories_name');
        });
    }

    public function down()
    {
        // Удаляем индексы
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_courses_category_id');
            $table->dropIndex('idx_courses_name');
            $table->dropIndex('idx_courses_category_name');
            $table->dropIndex('idx_courses_subcategory');
            $table->dropIndex('idx_courses_type');
            $table->dropIndex('idx_courses_direction');
        });

        Schema::table('position_course_requirements', function (Blueprint $table) {
            $table->dropIndex('idx_pcr_position_id');
            $table->dropIndex('idx_pcr_course_id');
            $table->dropIndex('idx_pcr_is_required');
            $table->dropIndex('idx_pcr_position_course');
            $table->dropIndex('idx_pcr_position_required');
        });

        Schema::table('brigade_course_requirements', function (Blueprint $table) {
            $table->dropIndex('idx_bcr_brigade_id');
            $table->dropIndex('idx_bcr_course_id');
            $table->dropIndex('idx_bcr_brigade_course');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropIndex('idx_positions_category_id');
            $table->dropIndex('idx_positions_name');
        });

        Schema::table('brigades', function (Blueprint $table) {
            $table->dropIndex('idx_brigades_name');
        });

        Schema::table('course_categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_sort_order');
            $table->dropIndex('idx_categories_name');
        });
    }
};
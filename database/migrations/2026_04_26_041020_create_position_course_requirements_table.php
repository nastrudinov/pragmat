<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_course_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            
            $table->unique(['position_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_course_requirements');
    }
};
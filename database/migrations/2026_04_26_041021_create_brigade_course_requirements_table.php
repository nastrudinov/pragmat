<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brigade_course_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brigade_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            
            $table->unique(['brigade_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brigade_course_requirements');
    }
};
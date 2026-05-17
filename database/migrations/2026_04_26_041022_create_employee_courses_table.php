<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['active', 'expiring', 'expired', 'required', 'noData'])->default('required');
            $table->date('assigned_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->text('certificate_file_path')->nullable();
            $table->date('last_reminder_sent')->nullable();
            $table->timestamps();
            
            $table->unique(['employee_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_courses');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('training_events')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['registered', 'confirmed', 'attended', 'absent', 'cancelled'])->default('registered');
            $table->date('completion_date')->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['event_id', 'employee_id']);
            $table->index('employee_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_event_participants');
    }
};
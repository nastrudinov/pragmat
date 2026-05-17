<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->enum('format', ['onsite', 'online', 'hybrid'])->default('onsite');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('training_center', 200)->nullable();
            $table->enum('status', ['draft', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->decimal('cost', 10, 2)->nullable();
            $table->integer('max_participants')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['start_date', 'status']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_events');
    }
};
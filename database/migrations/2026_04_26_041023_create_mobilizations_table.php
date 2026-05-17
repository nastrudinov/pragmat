<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobilizations', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('object_name', 200)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'blocked', 'completed'])->default('active');
            $table->foreignId('current_stage_id')->nullable()->constrained('mobilization_stages')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobilizations');
    }
};
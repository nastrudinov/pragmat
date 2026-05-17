<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->onDelete('cascade');
            $table->string('username', 50)->unique();
            $table->string('password_hash', 255);
            $table->enum('role', ['admin', 'hr_manager', 'training_curator', 'user'])->default('user');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_accounts');
    }
};
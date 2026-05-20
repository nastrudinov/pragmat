<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'planned', 'confirmed', 'in_progress', 'completed', 'cancelled', 'awaiting_confirmation'])
                ->default('draft')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('training_events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'confirmed', 'in_progress', 'completed', 'cancelled', 'awaiting_confirmation'])
                ->default('draft')
                ->change();
        });
    }
};
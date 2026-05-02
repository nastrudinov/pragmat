<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('subcategory', 100)->nullable()->after('category_id');
            $table->string('type', 50)->nullable()->after('subcategory');
            $table->text('legal_basis')->nullable()->after('type');
            $table->string('direction', 100)->nullable()->after('legal_basis');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['subcategory', 'type', 'legal_basis', 'direction']);
        });
    }
};
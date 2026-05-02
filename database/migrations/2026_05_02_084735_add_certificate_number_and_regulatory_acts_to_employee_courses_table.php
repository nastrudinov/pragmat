<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_courses', function (Blueprint $table) {
            $table->string('certificate_number', 100)->nullable()->after('certificate_file_path');
            $table->text('regulatory_acts')->nullable()->after('certificate_number');
        });
    }

    public function down(): void
    {
        Schema::table('employee_courses', function (Blueprint $table) {
            $table->dropColumn(['certificate_number', 'regulatory_acts']);
        });
    }
};
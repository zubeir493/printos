<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['employee_pension_rate', 'employer_pension_rate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('employee_pension_rate', 5, 2)->default(7)->after('pension_enabled');
            $table->decimal('employer_pension_rate', 5, 2)->default(11)->after('employee_pension_rate');
        });
    }
};

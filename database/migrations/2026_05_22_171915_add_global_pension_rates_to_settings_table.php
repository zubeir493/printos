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
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('employee_pension_rate', 5, 2)->default(7)->after('workers_union_rate');
            $table->decimal('employer_pension_rate', 5, 2)->default(11)->after('employee_pension_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['employee_pension_rate', 'employer_pension_rate']);
        });
    }
};

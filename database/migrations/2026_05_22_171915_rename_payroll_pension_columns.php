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
        Schema::table('payroll_run_employees', function (Blueprint $table) {
            $table->renameColumn('pension_11', 'employer_pension_contribution');
            $table->renameColumn('pension_18', 'pension_contribution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_run_employees', function (Blueprint $table) {
            $table->renameColumn('employer_pension_contribution', 'pension_11');
            $table->renameColumn('pension_contribution', 'pension_18');
        });
    }
};

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
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->string('period_type')->default('monthly')->after('name')->index();
            $table->date('payroll_month')->nullable()->after('period_type')->index();
            $table->string('department')->nullable()->after('payroll_month')->index();
            $table->string('employment_type')->nullable()->after('department')->index();
            $table->json('selected_employee_ids')->nullable()->after('employment_type');
            $table->json('excluded_employee_ids')->nullable()->after('selected_employee_ids');
            $table->timestamp('prepared_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'period_type',
                'payroll_month',
                'department',
                'employment_type',
                'selected_employee_ids',
                'excluded_employee_ids',
                'prepared_at',
            ]);
        });
    }
};

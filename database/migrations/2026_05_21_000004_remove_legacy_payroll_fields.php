<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'overtime_multiplier')) {
                $table->decimal('overtime_multiplier', 8, 4)->default(1)->after('basic_salary');
            }

            foreach (['hourly_overtime_rate', 'holiday_overtime_rate'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('employee_salary_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_salary_histories', 'overtime_multiplier')) {
                $table->decimal('overtime_multiplier', 8, 4)->default(1)->after('basic_salary');
            }

            foreach (['hourly_overtime_rate', 'holiday_overtime_rate'] as $column) {
                if (Schema::hasColumn('employee_salary_histories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payroll_run_employees', function (Blueprint $table) {
            foreach ([
                'monthly_rate',
                'hourly_rate',
                'regular_hours',
                'holiday_overtime_hours',
                'regular_pay',
                'overtime_pay',
                'holiday_overtime_pay',
                'night_premium_pay',
                'unpaid_leave_deduction',
                'late_absence_deduction',
                'taxable_income',
                'employee_pension',
                'employer_pension',
                'paye_tax',
                'gross_pay',
            ] as $column) {
                if (Schema::hasColumn('payroll_run_employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        //
    }
};

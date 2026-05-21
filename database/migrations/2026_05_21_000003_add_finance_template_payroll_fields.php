<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_employees', function (Blueprint $table) {
            $columns = Schema::getColumnListing('payroll_run_employees');

            if (! in_array('basic_salary', $columns, true)) {
                $table->decimal('basic_salary', 15, 2)->default(0)->after('employee_id');
            }

            if (! in_array('time_on_duty', $columns, true)) {
                $table->decimal('time_on_duty', 10, 2)->default(0)->after('basic_salary');
            }

            if (! in_array('pay_per_hour', $columns, true)) {
                $table->decimal('pay_per_hour', 15, 4)->default(0)->after('time_on_duty');
            }

            if (! in_array('bonus', $columns, true)) {
                $table->decimal('bonus', 15, 2)->default(0)->after('pay_per_hour');
            }

            if (! in_array('transport_allowance', $columns, true)) {
                $table->decimal('transport_allowance', 15, 2)->default(0)->after('bonus');
            }

            if (! in_array('pension_11', $columns, true)) {
                $table->decimal('pension_11', 15, 2)->default(0)->after('transport_allowance');
            }

            if (! in_array('overtime_amount', $columns, true)) {
                $table->decimal('overtime_amount', 15, 2)->default(0)->after('overtime_hours');
            }

            if (! in_array('gross_earning', $columns, true)) {
                $table->decimal('gross_earning', 15, 2)->default(0)->after('overtime_amount');
            }

            if (! in_array('taxable_amount', $columns, true)) {
                $table->decimal('taxable_amount', 15, 2)->default(0)->after('gross_earning');
            }

            if (! in_array('income_tax', $columns, true)) {
                $table->decimal('income_tax', 15, 2)->default(0)->after('taxable_amount');
            }

            if (! in_array('penalty_hours', $columns, true)) {
                $table->decimal('penalty_hours', 10, 2)->default(0)->after('income_tax');
            }

            if (! in_array('penalty_amount', $columns, true)) {
                $table->decimal('penalty_amount', 15, 2)->default(0)->after('penalty_hours');
            }

            if (! in_array('pension_18', $columns, true)) {
                $table->decimal('pension_18', 15, 2)->default(0)->after('penalty_amount');
            }

            if (! in_array('loan', $columns, true)) {
                $table->decimal('loan', 15, 2)->default(0)->after('pension_18');
            }

            if (! in_array('workers_union', $columns, true)) {
                $table->decimal('workers_union', 15, 2)->default(0)->after('loan');
            }

            if (! in_array('total_deduction', $columns, true)) {
                $table->decimal('total_deduction', 15, 2)->default(0)->after('workers_union');
            }
        });
    }

    public function down(): void
    {
        //
    }
};

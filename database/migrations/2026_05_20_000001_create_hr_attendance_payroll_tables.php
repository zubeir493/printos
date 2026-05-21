<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('attendance_device_id')->nullable()->unique()->after('employee_id');
            $table->string('employment_type')->default('permanent')->after('status');
            $table->string('tax_id')->nullable()->after('position');
            $table->boolean('pension_enabled')->default(true)->after('tax_id');
            $table->decimal('employee_pension_rate', 5, 2)->default(7)->after('pension_enabled');
            $table->decimal('employer_pension_rate', 5, 2)->default(11)->after('employee_pension_rate');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('expected_minutes')->default(480);
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('overtime_eligible')->default(true);
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_working_day')->default(true);
            $table->timestamps();

            $table->unique(['work_schedule_id', 'day_of_week']);
        });

        Schema::create('employee_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from', 'effective_until'], 'employee_schedule_date_index');
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date')->unique();
            $table->string('type')->default('public');
            $table->boolean('is_paid')->default(true);
            $table->boolean('counts_as_holiday_overtime')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->string('report_type')->default('punch_logs');
            $table->string('shift_type')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->foreignId('attendance_log_id')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->dateTime('punched_at');
            $table->string('type')->default('auto');
            $table->string('source')->default('manual');
            $table->foreignId('attendance_import_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'start_date', 'end_date']);
        });

        Schema::create('attendance_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('expected_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('regular_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('holiday_minutes')->default(0);
            $table->unsignedSmallInteger('night_minutes')->default(0);
            $table->unsignedSmallInteger('absence_minutes')->default(0);
            $table->unsignedSmallInteger('paid_leave_minutes')->default(0);
            $table->unsignedSmallInteger('unpaid_leave_minutes')->default(0);
            $table->string('status')->default('absent');
            $table->json('calculation_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });

        Schema::create('attendance_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_import_row_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('fp_no');
            $table->string('schedule_name')->nullable();
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->decimal('day_fraction', 5, 2)->default(0);
            $table->string('status')->nullable();
            $table->string('exception')->nullable();
            $table->text('correction_reason')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
        });

        Schema::create('attendance_period_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('shift_type')->default('normal');
            $table->decimal('work_days', 8, 2)->default(0);
            $table->decimal('actual_days', 8, 2)->default(0);
            $table->decimal('absent_days', 8, 2)->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_minutes')->default(0);
            $table->unsignedInteger('lunch_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('holiday_days', 8, 2)->default(0);
            $table->decimal('leave_days', 8, 2)->default(0);
            $table->decimal('dayoff_days', 8, 2)->default(0);
            $table->decimal('work_time_hours', 10, 2)->default(0);
            $table->decimal('work_percentage', 8, 2)->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period_start', 'period_end', 'shift_type'], 'attendance_period_summary_unique');
        });

        Schema::create('payroll_tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('jurisdiction')->default('ET');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_tax_rule_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_income', 15, 2);
            $table->decimal('max_income', 15, 2)->nullable();
            $table->decimal('rate', 5, 2);
            $table->decimal('deduction', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_run_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('time_on_duty', 10, 2)->default(0);
            $table->decimal('pay_per_hour', 15, 4)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('transport_allowance', 15, 2)->default(0);
            $table->decimal('pension_11', 15, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->decimal('gross_earning', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('income_tax', 15, 2)->default(0);
            $table->decimal('penalty_hours', 10, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('pension_18', 15, 2)->default(0);
            $table->decimal('loan', 15, 2)->default(0);
            $table->decimal('workers_union', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->json('calculation_snapshot')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payroll_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('code');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_line_items');
        Schema::dropIfExists('payroll_run_employees');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_tax_brackets');
        Schema::dropIfExists('payroll_tax_rules');
        Schema::dropIfExists('attendance_period_summaries');
        Schema::dropIfExists('attendance_segments');
        Schema::dropIfExists('attendance_daily_summaries');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendance_import_rows');
        Schema::dropIfExists('attendance_imports');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_schedule_assignments');
        Schema::dropIfExists('work_schedule_days');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('shifts');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'tax_id',
                'attendance_device_id',
                'employment_type',
                'pension_enabled',
                'employee_pension_rate',
                'employer_pension_rate',
            ]);
        });
    }
};

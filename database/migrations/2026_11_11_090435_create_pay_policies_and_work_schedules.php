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
        Schema::create('pay_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedSmallInteger('monthly_day_divisor')->default(30);
            $table->unsignedSmallInteger('standard_daily_minutes')->default(480);
            $table->decimal('day_overtime_multiplier', 8, 4)->default(1.5);
            $table->decimal('night_overtime_multiplier', 8, 4)->default(1.75);
            $table->decimal('rest_overtime_multiplier', 8, 4)->default(2);
            $table->decimal('holiday_overtime_multiplier', 8, 4)->default(2.5);
            $table->time('night_start')->default('22:00:00');
            $table->time('night_end')->default('06:00:00');
            $table->boolean('deduct_lateness')->default(false);
            $table->boolean('prorate_allowance')->default(false);
            $table->unsignedSmallInteger('overtime_increment_minutes')->default(1);
            $table->unsignedSmallInteger('maximum_daily_overtime_minutes')->default(240);
            $table->unsignedSmallInteger('maximum_weekly_overtime_minutes')->default(720);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['code', 'version']);
        });
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['work_schedule_id', 'day_of_week']);
        });
        Schema::create('employee_work_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('pay_policy_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('reason');
            $table->timestamps();
            $table->index(['employee_id', 'effective_from', 'effective_to'], 'employee_work_dates');
        });
        Schema::create('employee_shift_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });
        Schema::table('shifts', function (Blueprint $table) {
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->boolean('break_paid')->default(false);
            $table->unsignedSmallInteger('capture_before_minutes')->default(120);
            $table->unsignedSmallInteger('capture_after_minutes')->default(240);
        });
        Schema::table('employee_salary_histories', function (Blueprint $table) {
            $table->decimal('transport_allowance', 15, 2)->nullable();
            $table->boolean('pension_enabled')->nullable();
        });
        Schema::table('payroll_overtime_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('payroll_run_id')->nullable()->change();
            $table->unsignedBigInteger('overtime_rule_id')->nullable()->change();
            $table->foreignId('pay_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('category')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('calculation_snapshot')->nullable();
            $table->unique(['employee_id', 'source_key'], 'overtime_employee_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_overtime_entries', function (Blueprint $table) {
            $table->dropUnique('overtime_employee_source');
            $table->dropConstrainedForeignId('pay_policy_id');
            $table->dropColumn(['category', 'started_at', 'ended_at', 'calculation_snapshot']);
        });
        Schema::table('employee_salary_histories', fn (Blueprint $table) => $table->dropColumn(['transport_allowance', 'pension_enabled']));
        Schema::table('shifts', fn (Blueprint $table) => $table->dropColumn(['break_start', 'break_end', 'break_paid', 'capture_before_minutes', 'capture_after_minutes']));
        Schema::dropIfExists('employee_shift_overrides');
        Schema::dropIfExists('employee_work_assignments');
        Schema::dropIfExists('work_schedule_days');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('pay_policies');
    }
};

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
        Schema::create('payroll_overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('overtime_rule_id')->constrained()->restrictOnDelete();
            $table->date('date')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_key');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('minutes')->default(0);
            $table->decimal('hours', 10, 2)->default(0);
            $table->decimal('hourly_rate', 15, 4)->default(0);
            $table->decimal('multiplier', 8, 4)->default(1);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('manual_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id', 'overtime_rule_id', 'source_key'], 'payroll_overtime_entries_unique_source');
            $table->index(['payroll_run_id', 'employee_id', 'status'], 'payroll_overtime_entries_review_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_overtime_entries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            $table->decimal('annual_allowance_days', 7, 2)->nullable();
        });
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->text('decision_reason')->nullable();
        });
        Schema::table('employee_loans', function (Blueprint $table): void {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('disbursement_payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->boolean('legacy_disbursement_confirmed')->default(false);
        });
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attendance_reviewed_at')->nullable();
            $table->foreignId('attendance_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('attendance_review_note')->nullable();
        });
        Schema::create('payroll_payment_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('method')->default('bank');
            $table->string('status')->default('generated');
            $table->json('beneficiaries');
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payment_batches');
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('attendance_reviewed_by');
            $table->dropColumn(['attendance_reviewed_at', 'attendance_review_note']);
        });
        Schema::table('employee_loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('disbursement_payment_id');
            $table->dropColumn(['approved_at', 'legacy_disbursement_confirmed']);
        });
        Schema::table('leave_requests', fn (Blueprint $table) => $table->dropColumn('decision_reason'));
        Schema::table('leave_types', fn (Blueprint $table) => $table->dropColumn('annual_allowance_days'));
    }
};

<?php

namespace App\Services\Hr;

use App\Enums\PaymentTransactionType;
use App\Exceptions\HrWorkflowException;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanInstallment;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RepayEmployeeLoan
{
    public function handle(EmployeeLoan $loan, string $method, ?int $bankId = null, ?float $amount = null): Payment
    {
        if (auth()->check()) {
            Gate::authorize('update', $loan);
        }
        if (! in_array($method, ['cash', 'bank', 'cheque'], true)) {
            throw new HrWorkflowException('Unsupported repayment method.');
        }

        if ($method === 'bank' && ! $bankId) {
            throw new HrWorkflowException('Select the bank account used for this repayment.');
        }

        return DB::transaction(function () use ($loan, $method, $bankId, $amount): Payment {
            $loan = EmployeeLoan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($loan->status === 'cancelled' || ! $loan->isFunded()) {
                throw new HrWorkflowException('Only disbursed loans can be repaid.');
            }
            $installments = $loan->installments()
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            $remainingBalance = $installments->sum(fn (EmployeeLoanInstallment $installment): float => max(0.0, (float) $installment->amount - (float) $installment->paid_amount));

            $amount = $amount ?? $remainingBalance;
            $amount = round((float) $amount, 2);

            if ($amount <= 0) {
                throw new HrWorkflowException('This loan has no pending installments to repay.');
            }

            if ($amount > $remainingBalance) {
                throw new HrWorkflowException('Repayment amount exceeds the outstanding balance.');
            }

            $payment = Payment::create([
                'payment_date' => now(),
                'amount' => $amount,
                'method' => $method,
                'bank_id' => $bankId,
                'reference' => 'Manual repayment for employee loan #'.$loan->id,
                'transaction_type' => PaymentTransactionType::EMPLOYEE_LOAN_REPAYMENT->value,
                'payment_type' => 'employee_loan_repayment',
                'payable_type' => EmployeeLoan::class,
                'payable_id' => $loan->id,
            ]);

            $remainingAmount = $amount;

            foreach ($installments as $installment) {
                $currentPaid = (float) $installment->paid_amount;
                $installmentRemaining = max(0.0, (float) $installment->amount - $currentPaid);

                if ($installmentRemaining <= 0) {
                    continue;
                }

                $appliedAmount = min($installmentRemaining, $remainingAmount);

                $payment->loanInstallments()->attach($installment->id, ['amount' => $appliedAmount]);

                $installment->update([
                    'paid_amount' => round($currentPaid + $appliedAmount, 2),
                    'status' => round($currentPaid + $appliedAmount, 2) >= round((float) $installment->amount, 2) ? 'paid' : 'pending',
                    'payment_id' => $payment->id,
                    'paid_at' => now(),
                ]);

                $remainingAmount = round($remainingAmount - $appliedAmount, 2);

                if ($remainingAmount <= 0) {
                    break;
                }
            }

            if ($remainingAmount > 0) {
                throw new HrWorkflowException('Unable to apply the full repayment amount to the loan installments.');
            }

            $loan->syncStatusFromInstallments();

            return $payment;
        });
    }
}

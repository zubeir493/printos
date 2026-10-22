<?php

namespace App\Services\Hr;

use App\Enums\PaymentTransactionType;
use App\Exceptions\HrWorkflowException;
use App\Models\EmployeeLoan;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DisburseEmployeeLoan
{
    public function handle(EmployeeLoan $loan, string $method, ?int $bankId = null): Payment
    {
        if (auth()->check()) {
            Gate::authorize('update', $loan);
        }

        return DB::transaction(function () use ($loan, $method, $bankId): Payment {
            $loan = EmployeeLoan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== 'approved' || $loan->isFunded()) {
                throw new HrWorkflowException('Approve the loan before disbursement; funded loans cannot be disbursed again.');
            }
            if (! in_array($method, ['cash', 'bank', 'cheque'], true)) {
                throw new HrWorkflowException('Unsupported disbursement method.');
            }
            $payment = Payment::create([
                'payment_date' => now(), 'amount' => $loan->amount, 'method' => $method, 'bank_id' => $bankId,
                'transaction_type' => PaymentTransactionType::EMPLOYEE_LOAN_DISBURSEMENT->value,
                'payment_type' => 'employee_loan_disbursement', 'payable_type' => EmployeeLoan::class, 'payable_id' => $loan->id,
                'reference' => 'Disbursement for employee loan #'.$loan->id,
            ]);
            $loan->update(['status' => 'active', 'disbursement_payment_id' => $payment->id]);

            return $payment;
        }, 3);
    }
}

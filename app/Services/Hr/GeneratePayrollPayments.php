<?php

namespace App\Services\Hr;

use App\Enums\PaymentTransactionType;
use App\Exceptions\HrWorkflowException;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Support\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GeneratePayrollPayments
{
    public function handle(PayrollRun $payrollRun, string $method = 'bank', ?int $bankId = null, ?string $paymentDate = null): PayrollRun
    {
        if (auth()->check()) {
            Gate::authorize('update', $payrollRun);
        }
        if ($payrollRun->status !== 'approved') {
            throw new HrWorkflowException('Payroll payments can only be generated after approval.');
        }

        if (! in_array($method, ['cash', 'bank', 'cheque'], true)) {
            throw new HrWorkflowException('Unsupported payroll payment method.');
        }

        if (PaymentMethod::requiresBank($method) && ! $bankId) {
            throw new HrWorkflowException('Select the bank account used to pay payroll.');
        }

        DB::transaction(function () use ($payrollRun, $method, $bankId, $paymentDate): void {
            $payrollRun = PayrollRun::query()->lockForUpdate()->findOrFail($payrollRun->id);
            if ($payrollRun->status !== 'approved') {
                throw new HrWorkflowException('Payroll payments can only be generated after approval.');
            }
            $payrollRun->setRelation('employees', $payrollRun->employees()->lockForUpdate()->with('employee')->get());
            $payablePayrollEmployees = $payrollRun->employees
                ->whereNull('payment_id')
                ->filter(fn ($payrollEmployee): bool => (float) $payrollEmployee->net_pay > 0);
            $requiredAmount = (float) $payablePayrollEmployees->sum('net_pay');

            if ($requiredAmount <= 0) {
                throw new HrWorkflowException('No payroll payments were generated.');
            }

            if (PaymentMethod::requiresBank($method)) {
                $bank = Bank::query()->lockForUpdate()->find($bankId);

                if (! $bank || $bank->status !== 'active' || (float) $bank->current_balance < $requiredAmount) {
                    throw new HrWorkflowException('The selected bank account does not have enough balance for this payroll.');
                }
            }

            $payment = Payment::create([
                'payment_date' => $paymentDate ?? $payrollRun->pay_date ?? now(),
                'amount' => $requiredAmount,
                'direction' => 'outbound',
                'method' => $method,
                'bank_id' => $bankId,
                'reference' => $this->reference($payrollRun),
                'transaction_type' => PaymentTransactionType::PAYROLL_PAYMENT->value,
                'payment_type' => 'payroll',
                'payable_type' => PayrollRun::class,
                'payable_id' => $payrollRun->id,
            ]);

            $payrollRun->employees()
                ->whereNull('payment_id')
                ->where('net_pay', '>', 0)
                ->update(['payment_id' => $payment->id]);

            $payrollRun->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        });

        return $payrollRun->refresh();
    }

    private function reference(PayrollRun $payrollRun): string
    {
        return sprintf(
            'Payroll for %s - %s',
            $payrollRun->period_start->format('Y-m-d'),
            $payrollRun->period_end->format('Y-m-d'),
        );
    }
}

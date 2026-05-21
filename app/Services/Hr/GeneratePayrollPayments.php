<?php

namespace App\Services\Hr;

use App\Enums\PaymentTransactionType;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GeneratePayrollPayments
{
    public function handle(PayrollRun $payrollRun, string $method = 'bank', ?int $bankId = null): PayrollRun
    {
        if ($payrollRun->status !== 'approved') {
            throw new RuntimeException('Payroll payments can only be generated after approval.');
        }

        if (! in_array($method, ['cash', 'bank', 'cheque'], true)) {
            throw new RuntimeException('Unsupported payroll payment method.');
        }

        if ($method === 'bank' && ! $bankId) {
            throw new RuntimeException('Select the bank account used to pay payroll.');
        }

        DB::transaction(function () use ($payrollRun, $method, $bankId): void {
            $payrollRun->loadMissing('employees.employee');
            $payablePayrollEmployees = $payrollRun->employees
                ->whereNull('payment_id')
                ->filter(fn ($payrollEmployee): bool => (float) $payrollEmployee->net_pay > 0);
            $requiredAmount = (float) $payablePayrollEmployees->sum('net_pay');

            if ($requiredAmount <= 0) {
                throw new RuntimeException('No payroll payments were generated.');
            }

            if ($method === 'bank') {
                $bank = Bank::query()->find($bankId);

                if (! $bank || (float) $bank->current_balance < $requiredAmount) {
                    throw new RuntimeException('The selected bank account does not have enough balance for this payroll.');
                }
            }

            $payment = Payment::create([
                'payment_date' => $payrollRun->pay_date ?? now(),
                'amount' => $requiredAmount,
                'direction' => 'outbound',
                'method' => $method,
                'bank_id' => $bankId,
                'reference' => $this->reference($payrollRun),
                'transaction_type' => PaymentTransactionType::PAYROLL_PAYMENT->value,
                'payment_type' => 'payroll',
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

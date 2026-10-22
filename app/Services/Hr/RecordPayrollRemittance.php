<?php

namespace App\Services\Hr;

use App\Exceptions\HrWorkflowException;
use App\Models\Payment;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecordPayrollRemittance
{
    public const COLUMNS = ['payroll_tax_remittance' => 'income_tax', 'payroll_pension_remittance' => 'pension_contribution', 'payroll_union_remittance' => 'workers_union'];

    public function outstanding(PayrollRun $run, string $type): float
    {
        $column = self::COLUMNS[$type] ?? throw new HrWorkflowException('Unsupported payroll liability.');

        return max(0, round((float) $run->employees()->sum($column) - (float) Payment::query()->where('payable_type', PayrollRun::class)->where('payable_id', $run->id)->where('transaction_type', $type)->whereNull('voided_at')->sum('amount'), 2));
    }

    public function handle(PayrollRun $run, string $type, float $amount, int $bankId, string $reference): Payment
    {
        Gate::authorize('update', $run);

        return DB::transaction(function () use ($run, $type, $amount, $bankId, $reference): Payment {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! in_array($run->status, ['approved', 'paid'], true) || blank($reference) || $amount <= 0 || ! is_finite($amount) || $amount > $this->outstanding($run, $type)) {
                throw new HrWorkflowException('Select a posted payroll, reference and an amount within the outstanding liability.');
            }

            return Payment::create(['payable_type' => PayrollRun::class, 'payable_id' => $run->id, 'transaction_type' => $type, 'payment_type' => 'payroll_remittance', 'amount' => $amount, 'method' => 'bank', 'bank_id' => $bankId, 'payment_date' => now(), 'reference' => $reference]);
        }, 3);
    }
}

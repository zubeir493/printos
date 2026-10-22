<?php

namespace App\Services\Hr;

use App\Exceptions\HrWorkflowException;
use App\Models\Bank;
use App\Models\PayrollPaymentBatch;
use App\Models\PayrollRun;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManagePayrollPaymentBatch
{
    public function prepare(PayrollRun $run, Bank $bank): PayrollPaymentBatch
    {
        Gate::authorize('update', $run);

        return DB::transaction(function () use ($run, $bank): PayrollPaymentBatch {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'approved' || $bank->status !== 'active') {
                throw new HrWorkflowException('An approved payroll and an active bank are required.');
            }
            if ($run->paymentBatches()->whereIn('status', ['generated', 'submitted'])->exists()) {
                throw new HrWorkflowException('Complete or fail the existing batch before preparing another.');
            }
            $rows = $run->employees()->with('employee')->whereNull('payment_id')->where('net_pay', '>', 0)->get();
            if ($rows->isEmpty()) {
                throw new HrWorkflowException('No unpaid beneficiaries remain.');
            }
            $beneficiaries = $rows->map(function ($row): array {
                $snapshot = $row->calculation_snapshot['beneficiary'] ?? [];
                if (blank($snapshot['account_number'] ?? null) || blank($snapshot['bank_name'] ?? null)) {
                    throw new HrWorkflowException('A beneficiary is missing approved bank details. Correct the employee and use the controlled payroll correction flow.');
                }

                return [...$snapshot, 'currency_code' => $row->calculation_snapshot['currency_code'] ?? Money::currencyCode(), 'row_id' => $row->id, 'amount' => (float) $row->net_pay];
            })->all();

            return $run->paymentBatches()->create(['bank_id' => $bank->id, 'beneficiaries' => $beneficiaries, 'amount' => $rows->sum('net_pay'), 'created_by' => auth()->id()]);
        }, 3);
    }

    public function transition(PayrollPaymentBatch $batch, string $status, string $reference, string $note = ''): PayrollPaymentBatch
    {
        Gate::authorize('update', $batch->payrollRun);

        return DB::transaction(function () use ($batch, $status, $reference, $note): PayrollPaymentBatch {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($batch->payroll_run_id);
            $batch = PayrollPaymentBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $allowed = ['generated' => ['submitted', 'failed'], 'submitted' => ['settled', 'failed']];
            if (! in_array($status, $allowed[$batch->status] ?? [], true) || blank($reference) || ($status === 'failed' && blank($note))) {
                throw new HrWorkflowException('Invalid batch transition or missing bank reference.');
            }
            if ($status === 'settled') {
                $expected = $run->employees()->whereNull('payment_id')->where('net_pay', '>', 0)->get();
                if ($expected->pluck('id')->sort()->values()->all() !== collect($batch->beneficiaries)->pluck('row_id')->sort()->values()->all() || round((float) $expected->sum('net_pay'), 2) !== round((float) $batch->amount, 2)) {
                    throw new HrWorkflowException('Unpaid payroll rows differ from the submitted batch. Fail and regenerate the batch.');
                }
                app(GeneratePayrollPayments::class)->handle($run, $batch->method, $batch->bank_id, today()->toDateString());
                $batch->payment_id = $run->employees()->whereIn('id', collect($batch->beneficiaries)->pluck('row_id'))->whereNotNull('payment_id')->value('payment_id');
                $batch->settled_at = now();
            }
            if ($status === 'submitted') {
                $batch->submitted_at = now();
            }
            $batch->fill(['status' => $status, 'reference' => $reference, 'note' => $note])->save();

            return $batch->refresh();
        }, 3);
    }
}

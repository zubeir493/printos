<?php

namespace App\Observers;

use App\Models\PayrollRunEmployee;
use App\Services\Hr\RecalculatePayrollRegisterRow;
use RuntimeException;

class PayrollRunEmployeeObserver
{
    public function saving(PayrollRunEmployee $payrollRunEmployee): void
    {
        $payrollRun = $payrollRunEmployee->payrollRun()->first();

        if ($payrollRun?->status !== 'draft') {
            return;
        }

        app(RecalculatePayrollRegisterRow::class)->forModel($payrollRunEmployee, $payrollRun);
    }

    public function updating(PayrollRunEmployee $payrollRunEmployee): void
    {
        $lockedChanges = collect(array_keys($payrollRunEmployee->getDirty()))
            ->diff(['payment_id', 'updated_at']);

        if ($payrollRunEmployee->payrollRun()->value('status') !== 'draft' && $lockedChanges->isNotEmpty()) {
            throw new RuntimeException('Approved or paid payroll rows are locked.');
        }
    }
}

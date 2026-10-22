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

    public function creating(PayrollRunEmployee $row): void
    {
        if ($row->payrollRun()->value('status') !== 'draft') {
            throw new RuntimeException('Only draft payroll rows can be created.');
        }
    }

    public function deleting(PayrollRunEmployee $row): void
    {
        if ($row->payrollRun()->value('status') !== 'draft') {
            throw new RuntimeException('Posted payroll rows cannot be deleted.');
        }
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

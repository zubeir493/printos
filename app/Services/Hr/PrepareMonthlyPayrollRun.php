<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Support\FiscalCalendar;
use Carbon\CarbonImmutable;

class PrepareMonthlyPayrollRun
{
    public function __construct(private CalculatePayrollRun $calculator) {}

    public function handle(PayrollRun $payrollRun): PayrollRun
    {
        if ($payrollRun->period_type === 'monthly' && $payrollRun->payroll_month) {
            $period = FiscalCalendar::payrollPeriodForMonth($payrollRun->payroll_month);

            $payrollRun->forceFill([
                'name' => $this->nameFor($payrollRun, $period['start']->toDateString(), $period['end']->toDateString()),
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'pay_date' => $payrollRun->pay_date?->toDateString() ?? $period['pay_date']->toDateString(),
            ])->save();
        } else {
            $payrollRun->forceFill([
                'name' => $this->nameFor($payrollRun, $payrollRun->period_start->toDateString(), $payrollRun->period_end->toDateString()),
            ])->save();
        }

        $this->calculator->handle($payrollRun->refresh(), $this->employeeIds($payrollRun));

        $payrollRun->forceFill(['prepared_at' => now()])->save();

        return $payrollRun->refresh();
    }

    /**
     * @return array<int>|null
     */
    private function employeeIds(PayrollRun $payrollRun): ?array
    {
        $selected = collect($payrollRun->selected_employee_ids ?? [])->filter()->map(fn ($id): int => (int) $id);
        $excluded = collect($payrollRun->excluded_employee_ids ?? [])->filter()->map(fn ($id): int => (int) $id);

        $employmentTypes = PayrollRun::decodeEmploymentTypes($payrollRun->employment_type);

        $query = Employee::query()
            ->when($payrollRun->department, fn ($query): mixed => $query->where('department', $payrollRun->department))
            ->when($employmentTypes !== [], fn ($query): mixed => $query->whereIn('employment_type', $employmentTypes));

        if ($selected->isNotEmpty()) {
            $query->whereIn('id', $selected->all());
        }

        if ($excluded->isNotEmpty()) {
            $query->whereNotIn('id', $excluded->all());
        }

        if ($selected->isEmpty() && ! $payrollRun->department && $employmentTypes === [] && $excluded->isEmpty()) {
            return null;
        }

        return $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function createDraftForMonth(CarbonImmutable $month): PayrollRun
    {
        $period = FiscalCalendar::payrollPeriodForMonth($month);
        $payrollRun = PayrollRun::query()->firstOrCreate(
            [
                'period_type' => 'monthly',
                'payroll_month' => $period['start']->toDateString(),
            ],
            [
                'name' => FiscalCalendar::payrollMonthLabel($period['start']).' payroll',
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'pay_date' => $period['pay_date']->toDateString(),
                'status' => 'draft',
            ],
        );

        if ($payrollRun->status === 'draft') {
            return $this->handle($payrollRun);
        }

        return $payrollRun;
    }

    private function nameFor(PayrollRun $payrollRun, string $periodStart, string $periodEnd): string
    {
        if ($payrollRun->period_type === 'monthly' && $payrollRun->payroll_month) {
            return FiscalCalendar::payrollMonthLabel($payrollRun->payroll_month).' payroll';
        }

        return 'Payroll: '.FiscalCalendar::formatDateRange($periodStart, $periodEnd);
    }
}

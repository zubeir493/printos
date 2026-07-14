<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Services\Hr\PrepareMonthlyPayrollRun;
use App\Support\FiscalCalendar;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['period_type'] ?? null) === 'monthly' && filled($data['payroll_month'] ?? null)) {
            $period = FiscalCalendar::payrollPeriodForMonth($data['payroll_month']);

            $data['period_start'] = $period['start']->toDateString();
            $data['period_end'] = $period['end']->toDateString();
            $data['pay_date'] ??= $period['pay_date']->toDateString();
            $data['name'] = FiscalCalendar::payrollMonthLabel($data['payroll_month']).' payroll';
        } else {
            $periodRange = PayrollRunResource::parsePeriodRange($data['period_range'] ?? null);
            $data['period_start'] = $periodRange['start'] ?? $data['period_start'];
            $data['period_end'] = $periodRange['end'] ?? $data['period_end'];
            $data['name'] = 'Payroll: '.FiscalCalendar::formatDateRange($data['period_start'], $data['period_end']);
        }

        unset($data['period_range']);

        return $data;
    }

    protected function afterCreate(): void
    {
        app(PrepareMonthlyPayrollRun::class)->handle($this->record);
    }
}

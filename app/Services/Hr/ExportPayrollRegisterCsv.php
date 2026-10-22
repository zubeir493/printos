<?php

namespace App\Services\Hr;

use App\Models\PayrollRun;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportPayrollRegisterCsv
{
    public function download(PayrollRun $payrollRun): StreamedResponse
    {
        $fileName = 'payroll-register-'.$payrollRun->id.'.csv';

        return response()->streamDownload(
            fn () => print $this->csv($payrollRun),
            $fileName,
            ['Content-Type' => 'text/csv']
        );
    }

    public function csv(PayrollRun $payrollRun): string
    {
        if (auth()->check()) {
            Gate::authorize('view', $payrollRun);
        }
        $payrollRun->loadMissing('employees.employee');

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [$payrollRun->employees->first()?->calculation_snapshot['employer']['company_name'] ?? Setting::getSettings()->company_name]);
        fputcsv($handle, [($payrollRun->department ? $payrollRun->department.' ' : '').'Payroll Register']);
        fputcsv($handle, ['For the Period of '.$payrollRun->period_start->format('Y-m-d').' - '.$payrollRun->period_end->format('Y-m-d')]);
        fputcsv($handle, [
            'No',
            'Employee Name',
            'Title',
            'Basic Salary',
            'Time On Duty',
            'Pay Per Hour',
            'Bonus',
            'Trans Allow',
            'Employer Pension',
            'Over Time Hrs',
            'Over Time Amt',
            'Gross Earning',
            'Taxable Amount',
            'Income Tax',
            'Penalty Hrs',
            'Penalty Amt',
            'Pension Contribution',
            'Loan',
            'Workers Union',
            'Total Deduction',
            'Net Pay',
            'Signature',
        ]);

        foreach ($payrollRun->employees as $index => $employeePayroll) {
            fputcsv($handle, [
                $index + 1,
                $this->safe($employeePayroll->calculation_snapshot['beneficiary']['name'] ?? $employeePayroll->employee?->full_name),
                $this->safe($employeePayroll->calculation_snapshot['beneficiary']['position'] ?? $employeePayroll->employee?->position),
                $this->money($employeePayroll->basic_salary),
                $this->hours($employeePayroll->time_on_duty),
                $this->money($employeePayroll->pay_per_hour),
                $this->money($employeePayroll->bonus),
                $this->money($employeePayroll->transport_allowance),
                $this->money($employeePayroll->employer_pension_contribution),
                $this->hours($employeePayroll->overtime_hours),
                $this->money($employeePayroll->overtime_amount),
                $this->money($employeePayroll->gross_earning),
                $this->money($employeePayroll->taxable_amount),
                $this->money($employeePayroll->income_tax),
                $this->hours($employeePayroll->penalty_hours),
                $this->money($employeePayroll->penalty_amount),
                $this->money($employeePayroll->pension_contribution),
                $this->money($employeePayroll->loan),
                $this->money($employeePayroll->workers_union),
                $this->money($employeePayroll->total_deduction),
                $this->money($employeePayroll->net_pay),
                '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    private function safe(?string $value): string
    {
        return preg_match('/^[=+@\-]/', $value ?? '') ? "'".$value : (string) $value;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function hours(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}

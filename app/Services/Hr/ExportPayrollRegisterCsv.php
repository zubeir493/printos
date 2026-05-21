<?php

namespace App\Services\Hr;

use App\Models\PayrollRun;
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
        $payrollRun->loadMissing('employees.employee');

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['Nejashi Printing Press plc']);
        fputcsv($handle, ['Production Department Payroll Register']);
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
            'Pension Fund 11%',
            'Over Time Hrs',
            'Over Time Amt',
            'Gross Earning',
            'Taxable Amount',
            'Income Tax',
            'Penalty Hrs',
            'Penalty Amt',
            'Pension Fund 18%',
            'Loan',
            'Workers Union',
            'Total Deduction',
            'Net Pay',
            'Signature',
        ]);

        foreach ($payrollRun->employees as $index => $employeePayroll) {
            fputcsv($handle, [
                $index + 1,
                $employeePayroll->employee?->full_name,
                $employeePayroll->employee?->position,
                $this->money($employeePayroll->basic_salary),
                $this->hours($employeePayroll->time_on_duty),
                $this->money($employeePayroll->pay_per_hour),
                $this->money($employeePayroll->bonus),
                $this->money($employeePayroll->transport_allowance),
                $this->money($employeePayroll->pension_11),
                $this->hours($employeePayroll->overtime_hours),
                $this->money($employeePayroll->overtime_amount),
                $this->money($employeePayroll->gross_earning),
                $this->money($employeePayroll->taxable_amount),
                $this->money($employeePayroll->income_tax),
                $this->hours($employeePayroll->penalty_hours),
                $this->money($employeePayroll->penalty_amount),
                $this->money($employeePayroll->pension_18),
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

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function hours(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}

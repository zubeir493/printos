<?php

namespace App\Services\Hr;

use App\Models\Bank;
use App\Models\PayrollRun;
use App\Support\Money;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportPayrollBankAdvice
{
    public function download(PayrollRun $payrollRun, Bank $bank): StreamedResponse
    {
        return response()->streamDownload(
            fn (): int|false => print $this->csv($payrollRun, $bank),
            $this->filename($payrollRun, $bank),
            ['Content-Type' => 'text/csv'],
        );
    }

    public function csv(PayrollRun $payrollRun, Bank $bank): string
    {
        $payrollRun->loadMissing('employees.employee');
        $profile = $this->profile($bank);
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $profile['headers']);

        foreach ($payrollRun->employees->whereNull('payment_id')->where('net_pay', '>', 0) as $row) {
            $employee = $row->employee;

            fputcsv($handle, match ($profile['key']) {
                'cbe' => [
                    $employee?->account_number,
                    $employee?->full_name,
                    number_format((float) $row->net_pay, 2, '.', ''),
                    'Payroll '.$payrollRun->period_end->format('Y-m-d'),
                ],
                default => [
                    $employee?->employee_id,
                    $employee?->full_name,
                    $employee?->bank_name,
                    $employee?->account_number,
                    number_format((float) $row->net_pay, 2, '.', ''),
                    Money::currencyCode(),
                ],
            });
        }

        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    /**
     * @return array{key: string, headers: array<int, string>}
     */
    private function profile(Bank $bank): array
    {
        $name = strtolower($bank->bank_name ?: $bank->name);

        if (str_contains($name, 'commercial bank') || str_contains($name, 'cbe')) {
            return [
                'key' => 'cbe',
                'headers' => ['Account Number', 'Beneficiary Name', 'Amount', 'Reason'],
            ];
        }

        return [
            'key' => 'generic',
            'headers' => ['Employee ID', 'Employee Name', 'Employee Bank', 'Account Number', 'Amount', 'Currency'],
        ];
    }

    private function filename(PayrollRun $payrollRun, Bank $bank): string
    {
        $bankName = str($bank->name)->slug()->value();

        return "payroll-bank-advice-{$bankName}-{$payrollRun->period_end->format('Y-m-d')}.csv";
    }
}

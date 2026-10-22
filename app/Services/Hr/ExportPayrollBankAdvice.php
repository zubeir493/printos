<?php

namespace App\Services\Hr;

use App\Models\Bank;
use App\Models\PayrollRun;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
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

    public function csv(PayrollRun $payrollRun, Bank $bank, ?int $batchId = null): string
    {
        if (auth()->check()) {
            Gate::authorize('view', $payrollRun);
        }
        if (! in_array($payrollRun->status, ['approved', 'paid'], true)) {
            throw new \RuntimeException('Approve payroll before exporting bank advice.');
        }
        $batch = $payrollRun->paymentBatches()->where('bank_id', $bank->id)->when($batchId, fn ($query) => $query->whereKey($batchId))->latest('id')->first();
        if (! $batch) {
            throw new \RuntimeException('Prepare a payment batch before downloading bank advice.');
        }
        $profile = $this->profile($bank);
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $profile['headers']);

        foreach ($batch->beneficiaries as $row) {
            fputcsv($handle, match ($profile['key']) {
                'cbe' => [$this->safe($row['account_number']), $this->safe($row['name']), number_format($row['amount'], 2, '.', ''), 'Payroll '.$payrollRun->period_end->format('Y-m-d')],
                default => [$this->safe($row['employee_id']), $this->safe($row['name']), $this->safe($row['bank_name']), $this->safe($row['account_number']), number_format($row['amount'], 2, '.', ''), $row['currency_code'] ?? Money::currencyCode()],
            });
        }

        rewind($handle);

        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @return array{key: string, headers: array<int, string>}
     */
    private function safe(?string $value): string
    {
        return preg_match('/^[=+@\-]/', $value ?? '') ? "'".$value : (string) $value;
    }

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

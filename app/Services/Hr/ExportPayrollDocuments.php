<?php

namespace App\Services\Hr;

use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportPayrollDocuments
{
    public function payslip(PayrollRunEmployee $row): StreamedResponse
    {
        Gate::authorize('view', $row->payrollRun);
        abort_unless(in_array($row->payrollRun->status, ['approved', 'paid', 'reversed'], true), 422, 'Approve payroll before issuing a payslip.');

        return $this->download('payslip-'.$row->id.'.csv', [
            ['Employee', $row->calculation_snapshot['beneficiary']['name'] ?? 'Historical employee'],
            ['Period', $row->payrollRun->period_start->toDateString().' - '.$row->payrollRun->period_end->toDateString()],
            ['Status', $row->payrollRun->status],
            ['Currency', $row->calculation_snapshot['currency_code'] ?? Money::currencyCode()],
            ...$row->lineItems->map(fn ($item): array => [$item->type, $item->description, $item->amount])->all(),
            ['Net pay', $row->net_pay],
        ]);
    }

    public function remittances(PayrollRun $run): StreamedResponse
    {
        Gate::authorize('view', $run);

        return $this->download('payroll-remittances-'.$run->id.'.csv', [
            ['Payroll', $run->id, $run->status],
            ['Liability', 'Original amount', 'Currency', 'Outstanding'],
            ['PAYE', $run->employees()->sum('income_tax'), Money::currencyCode(), app(RecordPayrollRemittance::class)->outstanding($run, 'payroll_tax_remittance')],
            ['Pension (employee and employer)', $run->employees()->sum('pension_contribution'), Money::currencyCode(), app(RecordPayrollRemittance::class)->outstanding($run, 'payroll_pension_remittance')],
            ['Workers union', $run->employees()->sum('workers_union'), Money::currencyCode(), app(RecordPayrollRemittance::class)->outstanding($run, 'payroll_union_remittance')],
        ]);
    }

    /** @param array<array<mixed>> $rows */
    private function download(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\\-]/', $value) ? "'".$value : $value, $row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

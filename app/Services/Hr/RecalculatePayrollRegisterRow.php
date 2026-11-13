<?php

namespace App\Services\Hr;

use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Setting;

class RecalculatePayrollRegisterRow
{
    public function __construct(private CalculatePayrollTax $taxCalculator) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function forData(array $data, ?PayrollRun $payrollRun = null): array
    {
        $snapshot = is_array($data['calculation_snapshot'] ?? null) ? $data['calculation_snapshot'] : [];
        $basicSalary = (float) ($data['basic_salary'] ?? 0);
        $bonus = (float) ($data['bonus'] ?? 0);
        $transportAllowance = (float) ($data['transport_allowance'] ?? 0);
        $overtimeAmount = round(max(0, (float) ($data['overtime_amount'] ?? 0)), 2);
        $loan = (float) ($data['loan'] ?? 0);
        $manualEarnings = collect($snapshot['manual_earnings'] ?? [])
            ->sum(fn (array $item): float => max(0, (float) ($item['amount'] ?? 0)));
        $manualDeductions = collect($snapshot['manual_deductions'] ?? [])
            ->sum(fn (array $item): float => max(0, (float) ($item['amount'] ?? 0)));
        $payPerHour = (float) ($data['pay_per_hour'] ?? 0);
        $pensionEnabled = (bool) ($snapshot['pension_enabled'] ?? true);
        $settings = Setting::getSettings();
        $employeePensionRate = (float) ($snapshot['employee_pension_rate'] ?? $settings->employee_pension_rate);
        $employerPensionRate = (float) ($snapshot['employer_pension_rate'] ?? $settings->employer_pension_rate);
        $unionEnabled = (bool) ($snapshot['union_enabled'] ?? $settings->workers_union_enabled);
        $unionRate = (float) ($snapshot['union_rate'] ?? $settings->workers_union_rate);

        $pensionBase = (float) ($snapshot['pension_base'] ?? ($pensionEnabled ? $basicSalary : 0));
        $employerPensionContribution = round($pensionBase * ($employerPensionRate / 100), 2);
        $penaltyAmount = 0.0;
        $grossEarning = round($basicSalary + $bonus + $transportAllowance + $overtimeAmount + $manualEarnings, 2);
        $taxableAmount = max(0, $grossEarning);
        $tax = $this->taxCalculator->handle($taxableAmount, $payrollRun?->getRawOriginal('period_end') ?? now()->toDateString(), $snapshot['tax']['rule_id'] ?? null);
        $incomeTax = $tax['amount'];
        $pensionContribution = round($pensionBase * ($employeePensionRate / 100), 2);
        $workersUnion = $unionEnabled ? round($basicSalary * ($unionRate / 100), 2) : 0.0;
        $totalDeduction = round($incomeTax + $penaltyAmount + $pensionContribution + $loan + $workersUnion + $manualDeductions, 2);
        $netPay = max(0, round($grossEarning - $totalDeduction, 2));

        return [
            ...$data,
            'pay_per_hour' => $payPerHour,
            'employer_pension_contribution' => $employerPensionContribution,
            'overtime_amount' => $overtimeAmount,
            'gross_earning' => $grossEarning,
            'taxable_amount' => $taxableAmount,
            'income_tax' => $incomeTax,
            'penalty_amount' => $penaltyAmount,
            'penalty_hours' => 0,
            'pension_contribution' => $pensionContribution,
            'workers_union' => $workersUnion,
            'total_deduction' => $totalDeduction,
            'net_pay' => $netPay,
            'calculation_snapshot' => [
                ...$snapshot,
                'tax' => $tax,
                'union_enabled' => $unionEnabled,
                'union_rate' => $unionRate,
            ],
        ];
    }

    public function forModel(PayrollRunEmployee $row, ?PayrollRun $payrollRun = null): void
    {
        $data = $this->forData($row->attributesToArray(), $payrollRun);

        foreach ($data as $key => $value) {
            if ($row->isFillable($key)) {
                $row->{$key} = $value;
            }
        }
    }
}

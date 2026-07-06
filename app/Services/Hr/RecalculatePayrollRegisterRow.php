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
        $penaltyHours = (float) ($data['penalty_hours'] ?? 0);
        $loan = (float) ($data['loan'] ?? 0);
        $manualEarnings = collect($snapshot['manual_earnings'] ?? [])
            ->sum(fn (array $item): float => max(0, (float) ($item['amount'] ?? 0)));
        $manualDeductions = collect($snapshot['manual_deductions'] ?? [])
            ->sum(fn (array $item): float => max(0, (float) ($item['amount'] ?? 0)));
        $fullBasicSalary = (float) ($snapshot['full_basic_salary'] ?? $basicSalary);
        $baseDays = max(1, (float) ($snapshot['base_days'] ?? 30));
        $payPerHour = round($fullBasicSalary / $baseDays / 8, 4);
        $penaltyHourlyRate = (float) ($snapshot['penalty_hourly_rate'] ?? ($fullBasicSalary / 30 / 8));
        $pensionEnabled = (bool) ($snapshot['pension_enabled'] ?? true);
        $settings = Setting::getSettings();
        $employeePensionRate = (float) ($snapshot['employee_pension_rate'] ?? $settings->employee_pension_rate);
        $employerPensionRate = (float) ($snapshot['employer_pension_rate'] ?? $settings->employer_pension_rate);
        $unionEnabled = (bool) ($snapshot['union_enabled'] ?? $settings->workers_union_enabled);
        $unionRate = (float) ($snapshot['union_rate'] ?? $settings->workers_union_rate);

        $employerPensionContribution = $pensionEnabled ? round($basicSalary * ($employerPensionRate / 100), 2) : 0.0;
        $penaltyAmount = round($penaltyHourlyRate * $penaltyHours, 2);
        $grossEarning = round($basicSalary + $bonus + $transportAllowance + $employerPensionContribution + $overtimeAmount + $manualEarnings, 2);
        $taxableAmount = max(0, round($grossEarning - $employerPensionContribution - $penaltyAmount, 2));
        $incomeTax = $this->taxAmount($taxableAmount, $payrollRun?->getRawOriginal('period_end') ?? now()->toDateString(), $snapshot);
        $pensionContribution = $pensionEnabled ? round($basicSalary * (($employeePensionRate + $employerPensionRate) / 100), 2) : 0.0;
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
            'pension_contribution' => $pensionContribution,
            'workers_union' => $workersUnion,
            'total_deduction' => $totalDeduction,
            'net_pay' => $netPay,
            'calculation_snapshot' => [
                ...$snapshot,
                'union_enabled' => $unionEnabled,
                'union_rate' => $unionRate,
                'penalty_hourly_rate' => $penaltyHourlyRate,
                'penalty_day_divisor' => 30,
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

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function taxAmount(float $taxableAmount, string $date, array $snapshot): float
    {
        if (isset($snapshot['tax']['rate'], $snapshot['tax']['deduction'])) {
            return round(max(0, ($taxableAmount * ((float) $snapshot['tax']['rate'] / 100)) - (float) $snapshot['tax']['deduction']), 2);
        }

        return $this->taxCalculator->handle($taxableAmount, $date)['amount'];
    }
}

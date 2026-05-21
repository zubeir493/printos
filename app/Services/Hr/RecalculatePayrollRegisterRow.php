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
        $overtimeHours = (float) ($data['overtime_hours'] ?? 0);
        $penaltyHours = (float) ($data['penalty_hours'] ?? 0);
        $loan = (float) ($data['loan'] ?? 0);
        $payPerHour = round($basicSalary / 30 / 8, 4);
        $overtimeMultiplier = (float) ($snapshot['overtime_multiplier'] ?? 1);
        $employeePensionRate = (float) ($snapshot['employee_pension_rate'] ?? 7);
        $employerPensionRate = (float) ($snapshot['employer_pension_rate'] ?? 11);
        $pensionEnabled = (bool) ($snapshot['pension_enabled'] ?? true);
        $settings = Setting::getSettings();
        $unionEnabled = (bool) ($snapshot['union_enabled'] ?? $settings->workers_union_enabled);
        $unionRate = (float) ($snapshot['union_rate'] ?? $settings->workers_union_rate);

        $pension11 = $pensionEnabled ? round($basicSalary * ($employerPensionRate / 100), 2) : 0.0;
        $overtimeAmount = round(($basicSalary / 24 / 8) * $overtimeHours * $overtimeMultiplier, 2);
        $penaltyAmount = round($payPerHour * $penaltyHours, 2);
        $grossEarning = round($basicSalary + $bonus + $transportAllowance + $pension11 + $overtimeAmount, 2);
        $taxableAmount = max(0, round($grossEarning - $pension11 - $penaltyAmount, 2));
        $incomeTax = $this->taxAmount($taxableAmount, $payrollRun?->getRawOriginal('period_end') ?? now()->toDateString(), $snapshot);
        $pension18 = $pensionEnabled ? round($basicSalary * (($employeePensionRate + $employerPensionRate) / 100), 2) : 0.0;
        $workersUnion = $unionEnabled ? round($basicSalary * ($unionRate / 100), 2) : 0.0;
        $totalDeduction = round($incomeTax + $penaltyAmount + $pension18 + $loan + $workersUnion, 2);

        return [
            ...$data,
            'pay_per_hour' => $payPerHour,
            'pension_11' => $pension11,
            'overtime_amount' => $overtimeAmount,
            'gross_earning' => $grossEarning,
            'taxable_amount' => $taxableAmount,
            'income_tax' => $incomeTax,
            'penalty_amount' => $penaltyAmount,
            'pension_18' => $pension18,
            'workers_union' => $workersUnion,
            'total_deduction' => $totalDeduction,
            'net_pay' => round($grossEarning - $totalDeduction, 2),
            'calculation_snapshot' => [
                ...$snapshot,
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

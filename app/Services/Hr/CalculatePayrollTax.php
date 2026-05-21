<?php

namespace App\Services\Hr;

use App\Models\PayrollTaxBracket;
use App\Models\PayrollTaxRule;
use RuntimeException;

class CalculatePayrollTax
{
    /**
     * @return array{amount: float, rule_id: int, bracket_id: int, rate: float, deduction: float}
     */
    public function handle(float $taxableIncome, string $date): array
    {
        $rule = PayrollTaxRule::query()
            ->effectiveOn($date)
            ->with('brackets')
            ->latest('effective_from')
            ->first();

        if (! $rule) {
            throw new RuntimeException('No active payroll tax rule is configured for this payroll date.');
        }

        $bracket = $rule->brackets
            ->sortBy('min_income')
            ->first(function (PayrollTaxBracket $bracket) use ($taxableIncome): bool {
                return $taxableIncome >= (float) $bracket->min_income
                    && ($bracket->max_income === null || $taxableIncome <= (float) $bracket->max_income);
            });

        if (! $bracket) {
            throw new RuntimeException('No payroll tax bracket matched the taxable income.');
        }

        $tax = max(0, ($taxableIncome * ((float) $bracket->rate / 100)) - (float) $bracket->deduction);

        return [
            'amount' => round($tax, 2),
            'rule_id' => $rule->id,
            'bracket_id' => $bracket->id,
            'rate' => (float) $bracket->rate,
            'deduction' => (float) $bracket->deduction,
        ];
    }
}

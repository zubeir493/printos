<?php

namespace App\Services\Hr;

use App\Exceptions\HrWorkflowException;
use App\Models\PayrollTaxBracket;
use App\Models\PayrollTaxRule;

class CalculatePayrollTax
{
    /**
     * @return array{amount: float, rule_id: int, bracket_id: int, rate: float, deduction: float}
     */
    public function handle(float $taxableIncome, string $date, ?int $ruleId = null): array
    {
        if (! is_finite($taxableIncome) || $taxableIncome < 0) {
            throw new HrWorkflowException('Taxable income must be finite and nonnegative.');
        }
        $rule = PayrollTaxRule::query()
            ->when($ruleId, fn ($query) => $query->whereKey($ruleId), fn ($query) => $query->effectiveOn($date))
            ->with('brackets')
            ->latest('effective_from')
            ->first();

        if (! $rule) {
            throw new HrWorkflowException('No active payroll tax rule is configured for this payroll date.');
        }

        $previousMaximum = null;
        foreach ($rule->brackets->sortBy('min_income')->values() as $index => $candidate) {
            if (($index === 0 && (float) $candidate->min_income !== 0.0)
                || ($index > 0 && ($previousMaximum === null || (float) $candidate->min_income <= $previousMaximum || (float) $candidate->min_income > $previousMaximum + 1))
                || ($candidate->max_income !== null && (float) $candidate->max_income < (float) $candidate->min_income)
                || (float) $candidate->rate < 0 || (float) $candidate->rate > 100 || (float) $candidate->deduction < 0) {
                throw new HrWorkflowException('Payroll tax brackets contain invalid boundaries, gaps or overlaps.');
            }
            $previousMaximum = $candidate->max_income === null ? null : (float) $candidate->max_income;
        }

        $bracket = $rule->brackets
            ->sortBy('min_income')
            ->first(function (PayrollTaxBracket $bracket) use ($taxableIncome): bool {
                return $bracket->max_income === null || $taxableIncome <= (float) $bracket->max_income;
            });

        if (! $bracket) {
            throw new HrWorkflowException('No payroll tax bracket matched the taxable income.');
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

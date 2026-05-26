<?php

namespace App\Services\CostEstimates;

use App\Models\Setting;

class CostEstimateCalculator
{
    /**
     * @param  array<int|string, array<string, mixed>>  $tasks
     * @return array{subtotal: float, tax_amount: float, total: float, tasks: array<int|string, array<string, mixed>>}
     */
    public function calculate(string $jobType, array $tasks): array
    {
        $normalizedTasks = collect($tasks)
            ->map(function (array $task): array {
                $quantity = max(1, (int) ($task['quantity'] ?? 1));
                $unitPrice = round((float) ($task['unit_price'] ?? 0), 2);
                $taskCost = round($quantity * $unitPrice, 2);

                return array_merge($task, [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'task_cost' => $taskCost,
                ]);
            })
            ->all();

        $subtotal = round((float) collect($normalizedTasks)->sum('task_cost'), 2);
        $taxAmount = round($subtotal * $this->taxRate(), 2);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => round($subtotal + $taxAmount, 2),
            'tasks' => $normalizedTasks,
        ];
    }

    private function taxRate(): float
    {
        $settings = Setting::getSettings();

        return $settings->vat_enabled ? (float) $settings->vat_rate / 100 : 0.0;
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\Bank;
use App\Support\Money;
use Filament\Widgets\ChartWidget;

class BankBalancesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    public function getHeading(): string
    {
        $totalBalance = Bank::query()
            ->where('status', 'active')
            ->sum('current_balance');

        return 'Current Bank Balance - '.Money::abbreviate($totalBalance, precision: 2);
    }

    protected function getData(): array
    {
        $banks = Bank::query()
            ->where('status', 'active')
            ->orderByDesc('current_balance')
            ->get(['name', 'bank_name', 'current_balance']);

        if ($banks->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Current Balance',
                        'data' => [0],
                        'backgroundColor' => ['#94a3b8'],
                        'borderColor' => ['#64748b'],
                    ],
                ],
                'labels' => ['No active bank accounts'],
            ];
        }

        $colors = [
            '#6366f1', // Indigo
            '#0ea5e9', // Sky
            '#0d9488', // Teal
            '#059669', // Emerald
            '#7c3aed', // Violet
            '#a78bfa', // Light violet
            '#38bdf8', // Light sky
            '#2dd4bf', // Light teal
            '#4f46e5', // Dark indigo
            '#475569', // Slate
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Current Balance',
                    'data' => $banks
                        ->map(fn (Bank $bank) => (float) $bank->current_balance)
                        ->all(),
                    'backgroundColor' => $banks
                        ->values()
                        ->map(fn ($bank, int $index) => $colors[$index % count($colors)])
                        ->all(),
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $banks
                ->map(fn (Bank $bank) => "{$bank->bank_name} - {$bank->name}: ".Money::abbreviate((float) $bank->current_balance, precision: 2))
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

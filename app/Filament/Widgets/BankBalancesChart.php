<?php

namespace App\Filament\Widgets;

use App\Models\Bank;
use Filament\Widgets\ChartWidget;

class BankBalancesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        $totalBalance = Bank::query()
            ->where('status', 'active')
            ->sum('current_balance');

        return 'Current Bank Balance - '.number_format((float) $totalBalance, 2).' Birr';
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
            '#0f766e',
            '#2563eb',
            '#f59e0b',
            '#dc2626',
            '#7c3aed',
            '#16a34a',
            '#0891b2',
            '#db2777',
            '#ea580c',
            '#475569',
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
                ->map(fn (Bank $bank) => "{$bank->bank_name} - {$bank->name}")
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

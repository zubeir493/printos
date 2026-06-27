<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Invoice;
use LaravelDaily\FilaWidgets\Data\CompletionRateWidgetData;
use LaravelDaily\FilaWidgets\Widgets\CompletionRateWidget;

class InvoiceCollectionRateWidget extends CompletionRateWidget
{
    protected static ?int $sort = 4;

    protected ?string $widgetLabel = 'Invoice Collection Rate';

    protected function getData(): CompletionRateWidgetData
    {
        $total = Invoice::query()->where('status', '!=', 'cancelled')->count();
        $collected = Invoice::query()->whereIn('status', ['paid', 'partial'])->count();

        if ($total === 0) {
            return new CompletionRateWidgetData(0, isEmpty: true);
        }

        return new CompletionRateWidgetData(
            value: round(($collected / $total) * 100, 1),
            description: "{$collected} of {$total} invoices paid or partial",
        );
    }

    protected function getThresholds(): array
    {
        return [
            ['threshold' => 60, 'color' => 'danger', 'label' => 'Weak'],
            ['threshold' => 85, 'color' => 'warning', 'label' => 'Follow up'],
            ['threshold' => 100, 'color' => 'success', 'label' => 'Healthy'],
        ];
    }
}

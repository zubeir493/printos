<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Partner;
use App\Services\Accounting\ReceivablesQuery;
use App\Support\Money;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class ReceivablesRiskWidget extends BreakdownWidget
{
    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Receivables Risk';

    protected bool $showDelta = false;

    protected ?int $itemLimit = 6;

    protected function getData(): BreakdownWidgetData
    {
        $balancesByPartner = app(ReceivablesQuery::class)->customerBalances();

        return BreakdownWidgetData::fromCollection(
            Partner::query()
                ->where('is_customer', true)
                ->joinSub($balancesByPartner, 'receivable_balances', fn ($join) => $join->on('receivable_balances.partner_id', '=', 'partners.id'))
                ->select('partners.name')
                ->selectRaw('receivable_balances.total_balance')
                ->where('receivable_balances.total_balance', '>', 0)
                ->orderByDesc('receivable_balances.total_balance')
                ->limit(6)
                ->get(),
            labelKey: 'name',
            valueKey: 'total_balance',
        );
    }

    protected function getWidgetCurrency(): string
    {
        return Money::currencyCode();
    }
}

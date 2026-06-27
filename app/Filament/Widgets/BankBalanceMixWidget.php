<?php

namespace App\Filament\Widgets;

use App\Models\Bank;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class BankBalanceMixWidget extends BreakdownWidget
{
    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Bank Balance Mix';

    protected string $widgetCurrency = 'ETB';

    protected bool $showDelta = false;

    protected bool $groupOther = true;

    protected ?int $itemLimit = 6;

    protected function getData(): BreakdownWidgetData
    {
        return BreakdownWidgetData::fromCollection(
            Bank::query()
                ->where('status', 'active')
                ->orderByDesc('current_balance')
                ->get(['name', 'current_balance']),
            labelKey: 'name',
            valueKey: 'current_balance',
        );
    }
}

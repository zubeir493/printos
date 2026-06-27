<?php

namespace App\Filament\Widgets;

use App\Models\Artwork;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use LaravelDaily\FilaWidgets\Data\BreakdownItemData;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class OperationalBottlenecksWidget extends BreakdownWidget
{
    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Operational Bottlenecks';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected bool $showDelta = false;

    protected function getData(): BreakdownWidgetData
    {
        return new BreakdownWidgetData([
            new BreakdownItemData('Draft jobs', (float) JobOrder::query()->where('status', 'draft')->count(), color: 'warning'),
            new BreakdownItemData('Pending artwork', (float) Artwork::query()->where('is_approved', false)->count(), color: 'primary'),
            new BreakdownItemData('Open purchase orders', (float) PurchaseOrder::query()->whereIn('status', ['draft', 'approved'])->count(), color: 'warning'),
            new BreakdownItemData('Overdue invoices', (float) Invoice::query()->whereDate('due_date', '<', today())->whereNotIn('status', ['paid', 'cancelled'])->count(), color: 'danger'),
        ]);
    }
}

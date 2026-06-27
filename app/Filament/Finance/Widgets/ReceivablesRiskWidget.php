<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesOrder;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class ReceivablesRiskWidget extends BreakdownWidget
{
    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Receivables Risk';

    protected string $widgetCurrency = 'ETB';

    protected bool $showDelta = false;

    protected ?int $itemLimit = 6;

    protected function getData(): BreakdownWidgetData
    {
        $paymentsByOrder = Payment::query()
            ->select('payable_id')
            ->selectRaw('SUM(amount) as paid_amount')
            ->where('payable_type', SalesOrder::class)
            ->whereNull('voided_at')
            ->groupBy('payable_id');

        $balancesByPartner = SalesOrder::query()
            ->leftJoinSub($paymentsByOrder, 'order_payments', fn ($join) => $join->on('order_payments.payable_id', '=', 'sales_orders.id'))
            ->select('sales_orders.partner_id')
            ->selectRaw('SUM(sales_orders.total - COALESCE(order_payments.paid_amount, 0)) as total_balance')
            ->groupBy('sales_orders.partner_id');

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
}

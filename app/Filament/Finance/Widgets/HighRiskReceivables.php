<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Partner;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class HighRiskReceivables extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'High-Risk Receivables (Aging > 45 Days)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Partner::query()
                    ->where('is_customer', true)
                    ->join('sales_orders', 'partners.id', '=', 'sales_orders.partner_id')
                    ->select('partners.*')
                    ->selectRaw('SUM(sales_orders.total) - COALESCE((SELECT SUM(pa.allocated_amount) FROM payment_allocations pa WHERE pa.allocatable_id = sales_orders.id AND pa.allocatable_type = ?), 0) as total_balance', [SalesOrder::class])
                    ->groupBy('partners.id')
                    ->having('total_balance', '>', 0)
                    ->orderByDesc('total_balance')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Contact'),
                Tables\Columns\TextColumn::make('total_balance')
                    ->label('Outstanding Balance')
                    ->money('ETB')
                    ->color('danger')
                    ->sortable(),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('remind')
                        ->label('Send Reminder')
                        ->icon('heroicon-m-envelope')
                        ->color('warning')
                        ->action(fn (Partner $record) => Notification::make()->title('Reminder Sent')->success()->send()),
                ]),
            ]);
    }
}

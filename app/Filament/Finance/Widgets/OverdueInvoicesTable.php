<?php

namespace App\Filament\Finance\Widgets;

use App\Models\SalesInvoice;
use App\Support\Money;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OverdueInvoicesTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Overdue Invoices';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesInvoice::query()
                    ->with('partner')
                    ->whereDate('due_date', '<', today())
                    ->where('status', '!=', 'paid')
                    ->orderBy('due_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::format($state)),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->defaultSort('due_date', 'desc');
    }
}

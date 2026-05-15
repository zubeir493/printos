<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UnallocatedPaymentsTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Unallocated Payments';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->with('partner')
                    ->select('payments.*')
                    ->selectRaw('COALESCE((SELECT SUM(pa.allocated_amount) FROM payment_allocations pa WHERE pa.payment_id = payments.id), 0) as allocated_total')
                    ->whereRaw('COALESCE((SELECT SUM(pa.allocated_amount) FROM payment_allocations pa WHERE pa.payment_id = payments.id), 0) < amount')
                    ->orderByDesc('payment_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('payment_number')
                    ->label('Payment')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Partner')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->date(),
                Tables\Columns\TextColumn::make('amount')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('allocated_total')
                    ->label('Allocated')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('unallocated_balance')
                    ->label('Open Balance')
                    ->state(fn ($record) => (float) $record->amount - (float) $record->allocated_total)
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->color('warning'),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}

<?php

namespace App\Filament\Operations\Widgets;

use App\Models\JobOrder;
use App\Support\Money;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class HighPriorityJobsTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'High-Priority Jobs';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobOrder::query()
                    ->with('partner')
                    ->where('advance_paid', true)
                    ->orderBy('submission_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('job_order_number')
                    ->label('Job Order')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Partner')
                    ->searchable(),
                Tables\Columns\TextColumn::make('submission_date')
                    ->date(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Value')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::format($state)),
            ])
            ->defaultSort('submission_date', 'desc');
    }
}

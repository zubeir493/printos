<?php

namespace App\Filament\Widgets;

use App\Models\JobOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AdminExceptionsTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Executive Exceptions';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobOrder::query()
                    ->with(['partner', 'jobOrderTasks'])
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('due_date')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('job_order_number')
                    ->label('Job Order')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->date()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('jobOrderTasks_count')
                    ->label('Tasks')
                    ->state(fn (JobOrder $record) => $record->jobOrderTasks->count())
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Value')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->paginated(false);
    }
}

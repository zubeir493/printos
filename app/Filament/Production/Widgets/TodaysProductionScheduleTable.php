<?php

namespace App\Filament\Production\Widgets;

use App\Models\ProductionPlanItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TodaysProductionScheduleTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'This Week\'s Production Schedule';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductionPlanItem::query()
                    ->with([
                        'jobOrderTask.jobOrder.partner',
                        'productionPlanMachine.machine',
                        'productionPlanMachine.productionPlan',
                    ])
                    ->whereHas('productionPlanMachine.productionPlan', function ($query) {
                        $query
                            ->whereDate('week_start', '<=', now()->endOfWeek())
                            ->whereDate('week_end', '>=', now()->startOfWeek());
                    })
                    ->orderByDesc('planned_quantity')
            )
            ->searchable(false)
            ->columns([
                Tables\Columns\TextColumn::make('productionPlanMachine.machine.name')
                    ->label('Machine')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jobOrderTask.jobOrder.job_order_number')
                    ->label('Job Order')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jobOrderTask.name')
                    ->label('Task')
                    ->searchable(),
                Tables\Columns\TextColumn::make('planned_quantity')
                    ->label('Planned Qty')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('productionPlanMachine.productionPlan.week_start')
                    ->label('Plan Week')
                    ->date(),
            ]);
    }
}

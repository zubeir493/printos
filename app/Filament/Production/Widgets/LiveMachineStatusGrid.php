<?php

namespace App\Filament\Production\Widgets;

use App\Models\Machine;
use App\Models\ProductionReportItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LiveMachineStatusGrid extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Live Machine Status';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(Machine::query())
            ->searchable(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Machine')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(function (Machine $record) {
                        $hasLogToday = ProductionReportItem::query()
                            ->join('production_report_machines', 'production_report_items.production_report_machine_id', '=', 'production_report_machines.id')
                            ->join('production_plan_machines', 'production_report_machines.production_plan_machine_id', '=', 'production_plan_machines.id')
                            ->where('production_plan_machines.machine_id', $record->id)
                            ->whereDate('production_report_items.date', now())
                            ->exists();

                        return $hasLogToday ? 'Running' : 'Idle';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Running' => 'success',
                        'Idle' => 'gray',
                        default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('last_output')
                    ->label('Last Output')
                    ->getStateUsing(function (Machine $record) {
                        return ProductionReportItem::query()
                            ->join('production_report_machines', 'production_report_items.production_report_machine_id', '=', 'production_report_machines.id')
                            ->join('production_plan_machines', 'production_report_machines.production_plan_machine_id', '=', 'production_plan_machines.id')
                            ->where('production_plan_machines.machine_id', $record->id)
                            ->latest('production_report_items.date')
                            ->value('production_report_items.actual_quantity') ?? 0;
                    })
                    ->suffix(' Units'),
            ])
            ->paginated(false);
    }
}

<?php

namespace App\Filament\Production\Widgets;

use App\Models\ProductionPlan;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MaterialShortageWarnings extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Material Shortage Warnings (Imminent)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductionPlan::query()
                    ->where('status', 'draft')
                    ->whereDate('week_start', '>=', now()->subWeek())
                    ->latest()
                    ->limit(3)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Plan')
                    ->formatStateUsing(fn ($state) => 'Plan #'.$state),
                Tables\Columns\TextColumn::make('week_start')
                    ->label('Week Start')
                    ->date(),
                Tables\Columns\TextColumn::make('week_end')
                    ->label('Week End')
                    ->date(),
                Tables\Columns\TextColumn::make('shortage_alert')
                    ->label('Alert')
                    ->badge()
                    ->color('warning')
                    ->default('Verify material coverage'),
            ])
            ->actions([
                Action::make('View Plan')
                    ->url(fn (ProductionPlan $record): string => '/production/production-plans/'.$record->id)
                    ->icon('heroicon-m-arrow-right'),
            ]);
    }
}

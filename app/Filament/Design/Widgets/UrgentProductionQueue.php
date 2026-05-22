<?php

namespace App\Filament\Design\Widgets;

use App\Models\JobOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UrgentProductionQueue extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Urgent Production Queue (Next 48 Hours)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobOrder::query()
                    ->where('status', 'active')
                    ->latest()
                    ->limit(10)
            )
            ->searchable(false)
            ->columns([
                Tables\Columns\TextColumn::make('job_order_number')
                    ->label('Job Order #')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('submission_date')
                    ->date()
                    ->label('Submitted'),
            ])
            ->defaultSort('submission_date', 'desc')
            ->actions([
                ActionGroup::make([
                    Action::make('View')
                        ->url(fn (JobOrder $record): string => '/admin/job-orders/'.$record->id)
                        ->icon('heroicon-m-eye'),
                ]),
            ]);
    }
}

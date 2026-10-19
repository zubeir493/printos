<?php

namespace App\Filament\Design\Widgets;

use App\Filament\Resources\JobOrders\JobOrderResource;
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
                    ->with('partner')->where('designer_id', auth()->id())->inProgress()->whereBetween('due_date', [today(), today()->addDays(2)])
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
                        ->url(fn (JobOrder $record): string => JobOrderResource::getUrl('view', ['record' => $record], panel: 'design'))
                        ->icon('heroicon-m-eye'),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Design\Widgets;

use App\Models\JobOrderTask;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AwaitingYourUploadTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Awaiting Your Upload';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobOrderTask::query()
                    ->with(['jobOrder.partner'])
                    ->whereDoesntHave('artworks')
                    ->latest()
            )
            ->searchable(false)
            ->columns([
                Tables\Columns\TextColumn::make('jobOrder.job_order_number')
                    ->label('Job Order')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jobOrder.partner.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Task')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

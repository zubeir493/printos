<?php

namespace App\Filament\Design\Widgets;

use App\Models\JobOrder;
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
                JobOrder::query()
                    ->with(['partner'])
                    ->where('designer_id', auth()->id())->where('status', 'design')->whereDoesntHave('artworks')
                    ->latest()
            )
            ->searchable(false)
            ->columns([
                Tables\Columns\TextColumn::make('job_order_number')
                    ->label('Job Order')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partner.name')
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

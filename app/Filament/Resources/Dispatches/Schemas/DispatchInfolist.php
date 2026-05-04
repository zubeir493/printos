<?php

namespace App\Filament\Resources\Dispatches\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DispatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dispatch Details')
                    ->schema([
                        TextEntry::make('jobOrder.job_order_number')
                            ->label('Job Order')
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('warehouse.name')
                            ->label('Dispatched From'),
                        TextEntry::make('delivery_date')
                            ->label('Delivery Date')
                            ->date(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->since()
                            ->color('gray'),
                        TextEntry::make('remarks')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Delivered Items')
                    ->schema([
                        RepeatableEntry::make('dispatchItems')
                            ->label('')
                            ->schema([
                                TextEntry::make('jobOrderTask.name')
                                    ->label('Task')
                                    ->weight('medium'),
                                TextEntry::make('quantity')
                                    ->label('Qty Dispatched')
                                    ->alignEnd(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}

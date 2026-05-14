<?php

namespace App\Filament\Resources\ProductionReports\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('production_plan')
                                    ->label('Production Plan')
                                    ->content(fn ($record): string => $record?->productionPlan
                                        ? $record->productionPlan->week_start->format('M d, Y').' - '.$record->productionPlan->week_end->format('M d, Y')
                                        : 'Not assigned'),
                                Placeholder::make('status_display')
                                    ->label('Status')
                                    ->content(fn ($record): string => ucfirst($record?->status ?? 'draft')),
                            ]),
                        Hidden::make('production_plan_id')
                            ->dehydrated(),
                        Hidden::make('status')
                            ->default('draft')
                            ->dehydrated(),
                    ])
                    ->compact()
                    ->columnSpan(4),
                Repeater::make('machines')
                    ->label('Machine Reports')
                    ->relationship()
                    ->schema([
                        Placeholder::make('machine_name')
                            ->label('Machine')
                            ->content(fn ($record): string => $record?->productionPlanMachine?->machine?->name ?? 'Machine'),

                        Repeater::make('items')
                            ->label('Production Records')
                            ->table([
                                TableColumn::make('Task')->width('220px')->alignLeft(),
                                TableColumn::make('Quantity')->alignLeft(),
                                TableColumn::make('Plates')->alignLeft(),
                                TableColumn::make('Rounds')->alignLeft(),
                                TableColumn::make('Date')->alignLeft()->width('200px'),
                            ])
                            ->relationship()
                            ->schema([
                                Placeholder::make('job_order_task')
                                    ->label('Task')
                                    ->content(fn ($record): string => $record?->productionPlanItem?->jobOrderTask?->name ?? 'Task'),
                                TextInput::make('actual_quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->extraAttributes(['class' => 'planvactualcolumn'])
                                    ->suffix(fn ($record): string => '/ '.number_format((float) ($record?->productionPlanItem?->planned_quantity ?? 0), 2)),
                                TextInput::make('plates_used')
                                    ->label('Plates')
                                    ->numeric()
                                    ->default(0)
                                    ->extraAttributes(['class' => 'planvactualcolumn'])
                                    ->suffix(fn ($record): string => '/ '.number_format((float) ($record?->productionPlanItem?->planned_plates ?? 0), 2)),
                                TextInput::make('rounds')
                                    ->label('Rounds')
                                    ->numeric()
                                    ->default(0)
                                    ->extraAttributes(['class' => 'planvactualcolumn'])
                                    ->suffix(fn ($record): string => '/ '.number_format((float) ($record?->productionPlanItem?->planned_rounds ?? 0), 2)),
                                DatePicker::make('date')
                                    ->required()
                                    ->default(now()),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->compact()
                            ->columnSpanFull()
                            ->addActionLabel(''),
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->columnSpanFull()
                    ->addActionLabel(''),
            ])->columns(7);
    }
}

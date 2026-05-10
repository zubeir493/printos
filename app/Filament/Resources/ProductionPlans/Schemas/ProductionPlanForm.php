<?php

namespace App\Filament\Resources\ProductionPlans\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('machines')
                    ->label('Machine Assignments')
                    ->relationship()
                    ->schema([
                        Select::make('machine_id')
                            ->relationship('machine', 'name')
                            ->required()
                            ->unique()
                            ->columnSpan(1),
                        Repeater::make('items')
                            ->label('Tasks for this machine')
                            ->relationship()
                            ->table([
                                TableColumn::make('Task')->alignLeft(),
                                TableColumn::make('Qty')->alignLeft(),
                                TableColumn::make('Plates')->alignLeft(),
                                TableColumn::make('Rounds')->alignLeft(),
                            ])
                            ->schema([
                                Select::make('job_order_task_id')
                                    ->relationship('jobOrderTask', 'name')
                                    ->unique()
                                    ->required(),
                                TextInput::make('planned_quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                TextInput::make('planned_plates')
                                    ->label('Plates')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('planned_rounds')
                                    ->label('Rounds')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(4)
                            ->compact()
                            ->columnSpanFull()
                            ->defaultItems(1)
                            ->addable(false)
                            ->extraItemActions([
                                Action::make('add_task')
                                    ->label('Add Task')
                                    ->icon('heroicon-o-plus')
                                    ->action(function (Repeater $component) {
                                        $state = $component->getState() ?? [];
                                        $state[] = [
                                            'job_order_task_id' => null,
                                            'planned_quantity' => 0,
                                            'planned_plates' => 0,
                                            'planned_rounds' => 0,
                                        ];
                                        $component->state($state);
                                    }),
                            ]),
                    ])
                    ->columnSpan(5)
                    ->defaultItems(1)
                    ->addable(false)
                    ->extraItemActions([
                        Action::make('add_machine')
                            ->label('Add Machine')
                            ->icon('heroicon-o-plus')
                            ->action(function (Repeater $component) {
                                $state = $component->getState() ?? [];
                                $state[] = [
                                    'machine_id' => null,
                                    'items' => [
                                        [
                                            'job_order_task_id' => null,
                                            'planned_quantity' => 0,
                                            'planned_plates' => 0,
                                            'planned_rounds' => 0,
                                        ],
                                    ],
                                ];
                                $component->state($state);
                            }),
                    ]),
                Section::make([
                    DatePicker::make('week_start')
                        ->default(now())
                        ->required(),
                    DatePicker::make('week_end')
                        ->default(now()->addWeek())
                        ->required(),
                    Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'approved' => 'Approved',
                        ])
                        ->default('draft')
                        ->required(),
                ])->columnSpan(2),
            ])->columns(7);
    }
}

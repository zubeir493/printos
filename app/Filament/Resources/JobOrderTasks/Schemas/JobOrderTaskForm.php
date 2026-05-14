<?php

namespace App\Filament\Resources\JobOrderTasks\Schemas;

use App\Models\InventoryItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JobOrderTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('job_order_id')
                    ->relationship('jobOrder', 'job_order_number')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('task_cost')
                    ->required()
                    ->label('Cost')
                    ->numeric()
                    ->suffix(' birr'),
                Textarea::make('instructions')
                    ->label('Design Brief / Instructions')
                    ->placeholder('Describe what needs to be designed, any specific requirements, references, or deadlines...')
                    ->rows(3),
                Placeholder::make('status_display')
                    ->label('Status')
                    ->weight('Bold')
                    ->content(fn ($record) => match ($record?->status) {
                        'pending' => 'Pending',
                        'design' => 'Design',
                        'production' => 'Production',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        default => 'Pending'
                    }),
                Repeater::make('paper')
                    ->label('Paper used for this task')
                    ->table([
                        TableColumn::make('Paper')->alignLeft(),
                        TableColumn::make('Required qty (sheets)')->alignLeft(),
                        TableColumn::make('Reserve qty (sheets)')->alignLeft(),
                    ])
                    ->compact()
                    ->schema([
                        Select::make('inventory_item_id')
                            ->label('Material')
                            ->options(InventoryItem::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $item = InventoryItem::find($state);
                                $set('base_unit', $item?->unit);
                            }),
                        TextInput::make('required_quantity')
                            ->numeric()
                            ->required()
                            ->default(0),

                        TextInput::make('reserve_quantity')
                            ->numeric()
                            ->default(0),

                        Hidden::make('base_unit'),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->columnSpanFull()
                    ->addActionLabel('Add paper')
                    ->extraItemActions([
                        Action::make('add_task')
                            ->label('Add Paper')
                            ->icon('heroicon-o-plus')
                            ->action(function (Repeater $component) {
                                $state = $component->getState() ?? [];
                                $state[] = [
                                    'inventory_item_id' => null,
                                    'required_quantity' => 0,
                                    'reserve_quantity' => 0,
                                    'base_unit' => null,
                                ];
                                $component->state($state);
                            }),
                    ])
                    ->addable(false)
                    ->reorderable(false)
                    ->minItems(1),
            ]);
    }
}

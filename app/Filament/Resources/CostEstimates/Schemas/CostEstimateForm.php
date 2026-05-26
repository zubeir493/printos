<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use App\Filament\Support\PanelAccess;
use App\Models\InventoryItem;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CostEstimateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('estimate_number')
                                ->label('Estimate #')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('job_type')
                                ->options([
                                    'books' => 'Books',
                                    'packages' => 'Packages',
                                    'labels' => 'Labels',
                                    'vouchers' => 'Vouchers',
                                ])
                                ->default('packages')
                                ->live()
                                ->required(),
                            Select::make('partner_id')
                                ->label('Customer')
                                ->relationship('partner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_customer', true))
                                ->searchable()
                                ->preload(),
                        ]),
                        Repeater::make('tasks')
                            ->relationship()
                            ->label('Tasks')
                            ->table([
                                TableColumn::make('Task')->alignLeft(),
                                TableColumn::make('Qty')->alignLeft(),
                                TableColumn::make('Size')->alignLeft(),
                                TableColumn::make('Unit price')->alignLeft(),
                                TableColumn::make('Total')->alignLeft(),
                            ])
                            ->schema([
                                TextInput::make('name')->required(),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->required()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTaskCost($get, $set)),
                                TextInput::make('size'),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('Birr')
                                    ->live(onBlur: true)
                                    ->required()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTaskCost($get, $set)),
                                TextInput::make('task_cost')
                                    ->numeric()
                                    ->readOnly()
                                    ->suffix('Birr')
                                    ->default(0),
                                Repeater::make('paper')
                                    ->label('Paper')
                                    ->table([
                                        TableColumn::make('Paper')->alignLeft(),
                                        TableColumn::make('Required qty')->alignLeft(),
                                        TableColumn::make('Reserve qty')->alignLeft(),
                                    ])
                                    ->compact()
                                    ->schema([
                                        Select::make('inventory_item_id')
                                            ->label('Material')
                                            ->options(fn (): array => InventoryItem::query()
                                                ->rawMaterials()
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->searchable()
                                            ->preload(),
                                        TextInput::make('required_quantity')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),
                                        TextInput::make('reserve_quantity')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),
                                    ])
                                    ->columnSpanFull(),
                                Repeater::make('deliverables')
                                    ->label('Deliverables')
                                    ->table([
                                        TableColumn::make('Name')->alignLeft(),
                                        TableColumn::make('Type')->alignLeft(),
                                    ])
                                    ->compact()
                                    ->schema([
                                        TextInput::make('label')->required(),
                                        Select::make('type')
                                            ->options([
                                                'artwork' => 'Artwork',
                                                'text_file' => 'Text File',
                                            ])
                                            ->default('artwork')
                                            ->required(),
                                    ])
                                    ->columnSpanFull(),
                                Textarea::make('instructions')->columnSpanFull(),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->required()
                            ->columnSpanFull(),
                        Section::make('Additional Services')
                            ->schema(self::servicesSchema())
                            ->compact(),
                        Textarea::make('remarks')->columnSpanFull(),
                    ])
                    ->columnSpan(3),
                Section::make()
                    ->schema([
                        Hidden::make('status')->default('draft'),
                        TextInput::make('subtotal')
                            ->numeric()
                            ->readOnly()
                            ->default(0)
                            ->suffix('Birr')
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->dehydratedWhenHidden(),
                        TextInput::make('tax_amount')
                            ->label('VAT')
                            ->numeric()
                            ->readOnly()
                            ->default(0)
                            ->suffix('Birr')
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->dehydratedWhenHidden(),
                        TextInput::make('total')
                            ->numeric()
                            ->readOnly()
                            ->default(0)
                            ->suffix('Birr')
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->dehydratedWhenHidden(),
                    ]),
            ])
            ->columns(4);
    }

    private static function updateTaskCost(Get $get, Set $set): void
    {
        $quantity = max(1, (int) ($get('quantity') ?? 1));
        $unitPrice = (float) ($get('unit_price') ?? 0);

        $set('task_cost', round($quantity * $unitPrice, 2));
    }

    private static function servicesSchema(): array
    {
        return [
            Grid::make(3)
                ->visible(fn (Get $get) => $get('job_type') === 'books')
                ->schema([
                    Group::make([
                        Checkbox::make('services.typing')->label('Typing'),
                        Checkbox::make('services.layout_design')->label('Layout Design'),
                        Checkbox::make('services.cover_design')->label('Cover Design'),
                        Checkbox::make('services.lamination')->label('Lamination'),
                    ])->columnSpan(1),
                    Group::make([
                        TextInput::make('services.page_no')->label('Number of Pages'),
                        TextInput::make('services.text_color_no')->label('Text Colors'),
                        TextInput::make('services.cover_color_no')->label('Cover Colors'),
                        Select::make('services.binding_type')
                            ->label('Binding Type')
                            ->options([
                                'saddle' => 'Saddle stitch',
                                'perfect' => 'Perfect Binding',
                                'hardcover' => 'Hardcover',
                            ]),
                    ])->columnSpan(2)->columns(2),
                ]),
            Grid::make(3)
                ->visible(fn (Get $get) => in_array($get('job_type'), ['packages', 'labels', 'vouchers'], true))
                ->schema([
                    Group::make([
                        Checkbox::make('services.new_design')->label('New Design'),
                        Checkbox::make('services.redesign')->label('Redesign'),
                        Checkbox::make('services.new_dielines')->label('New dielines'),
                        Checkbox::make('services.full_color')->label('Full color'),
                        Checkbox::make('services.one_side_print')->label('One side print'),
                        Checkbox::make('services.back_side_print')->label('Back side print'),
                    ])->columnSpan(1),
                    Group::make([
                        TextInput::make('services.amount_of_colors')->label('Amount of Colors'),
                        TextInput::make('services.printing_ups')->label('Printing Ups'),
                        TextInput::make('services.diecutting_ups')
                            ->label(fn (Get $get): string => $get('job_type') === 'vouchers' ? 'Numbering Ups' : 'Diecutting Ups'),
                        TextInput::make('services.pieces_per_sheet')->label('Pieces per sheet'),
                        CheckboxList::make('services.colors_used')
                            ->options([
                                'C' => 'C',
                                'M' => 'M',
                                'Y' => 'Y',
                                'K' => 'K',
                            ])
                            ->columns(4),
                    ])->columnSpan(2)->columns(2),
                ]),
        ];
    }
}

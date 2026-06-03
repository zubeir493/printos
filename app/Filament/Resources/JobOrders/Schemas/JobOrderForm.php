<?php

namespace App\Filament\Resources\JobOrders\Schemas;

use App\Filament\Support\Calculations;
use App\Filament\Support\PanelAccess;
use App\Models\InventoryItem;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as UtilitiesGet;
use Filament\Schemas\Components\Utilities\Set as UtilitiesSet;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class JobOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Grid::make()
                            ->schema([
                                Select::make('production_mode')
                                    ->options([
                                        'make_to_order' => 'Client Job',
                                        'make_to_stock' => 'Internal Job',
                                    ])
                                    ->default('make_to_order')
                                    ->live()
                                    ->required(),
                                Select::make('partner_id')
                                    ->label('Customer')
                                    ->relationship('partner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_customer', true))
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required(),
                                        TextInput::make('phone')
                                            ->required(),
                                        TextInput::make('email')
                                            ->email(),
                                        TextInput::make('address'),
                                        Hidden::make('is_customer')->default(true),
                                    ])
                                    ->preload()
                                    ->searchable()
                                    ->required(fn (UtilitiesGet $get) => $get('production_mode') !== 'make_to_stock')
                                    ->hidden(fn (UtilitiesGet $get) => $get('production_mode') === 'make_to_stock'),
                                Select::make('job_type')
                                    ->options([
                                        'books' => 'Books',
                                        'packages' => 'Packages',
                                        'vouchers' => 'Vouchers',
                                        'labels' => 'Labels',
                                    ])
                                    ->reactive()
                                    ->default('packages')
                                    ->required(),
                                DatePicker::make('submission_date')
                                    ->default(now())
                                    ->required(),

                                DatePicker::make('due_date')
                                    ->label('Payment Due Date')
                                    ->default(fn () => now()->addDays(30))
                                    ->required(),
                            ])
                            ->columns(3),
                        Repeater::make('jobOrderTasks')
                            ->label('List of Tasks')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->required(),

                                TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),

                                TextInput::make('size')
                                    ->label('Size'),

                                TextInput::make('task_cost')
                                    ->label('Cost')
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->required()
                                    ->minValue(0)
                                    ->live()
                                    ->afterStateUpdated(function (UtilitiesGet $get, UtilitiesSet $set) {
                                        Calculations::sumRepeater($get, $set, '../../jobOrderTasks', 'subtotal', 'task_cost');
                                        Calculations::updateTaxedTotal($get, $set, '../../subtotal', '../../tax_amount', '../../total');
                                    })
                                    ->afterStateHydrated(function (UtilitiesGet $get, UtilitiesSet $set) {
                                        Calculations::sumRepeater($get, $set, '../../jobOrderTasks', 'subtotal', 'task_cost');
                                        Calculations::updateTaxedTotal($get, $set, '../../subtotal', '../../tax_amount', '../../total');
                                    })
                                    ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                                    ->dehydratedWhenHidden(),

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
                                            ->options(fn (): array => InventoryItem::query()
                                                ->rawMaterials()
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->searchable()
                                            ->required()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $item = InventoryItem::query()
                                                    ->rawMaterials()
                                                    ->find($state);
                                                $set('base_unit', $item?->unit);
                                            }),
                                        TextInput::make('required_quantity')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->default(0),

                                        TextInput::make('reserve_quantity')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),

                                        Hidden::make('base_unit'),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(1)
                                    ->columnSpanFull()
                                    ->addable(false)
                                    ->reorderable(false)
                                    ->minItems(1)
                                    ->extraItemActions([
                                        Action::make('add_paper')
                                            ->label('Add Paper')
                                            ->icon('heroicon-o-plus')
                                            ->action(function (Repeater $component) {
                                                $state = $component->getState() ?? [];
                                                $state[(string) Str::uuid()] = [
                                                    'inventory_item_id' => null,
                                                    'required_quantity' => 0,
                                                    'reserve_quantity' => 0,
                                                    'base_unit' => null,
                                                ];
                                                $component->state($state);
                                            }),
                                    ]),
                                Repeater::make('deliverables')
                                    ->label('Required files for this task')
                                    ->table([
                                        TableColumn::make('Name')->alignLeft(),
                                        TableColumn::make('File Type')->alignLeft(),
                                    ])
                                    ->compact()
                                    ->columnSpanFull()
                                    ->addable(false)
                                    ->reorderable(false)
                                    ->defaultItems(1)
                                    ->extraItemActions([
                                        Action::make('add_deliverable')
                                            ->label('Add Deliverable')
                                            ->icon('heroicon-o-plus')
                                            ->action(function (Repeater $component) {
                                                $state = $component->getState() ?? [];
                                                $state[(string) Str::uuid()] = [
                                                    'label' => '',
                                                    'type' => 'artwork',
                                                ];
                                                $component->state($state);
                                            }),
                                    ])
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Deliverable')
                                            ->placeholder('Cover Artwork')
                                            ->required(),
                                        Select::make('type')
                                            ->label('Type')
                                            ->options([
                                                'artwork' => 'Artwork',
                                                'text_file' => 'Text File',
                                            ])
                                            ->required(),
                                    ]),
                            ])
                            ->columns(4)
                            ->required()
                            ->defaultItems(1)
                            ->addable(false)
                            ->extraItemActions([
                                Action::make('add_task')
                                    ->label('Add Task')
                                    ->icon('heroicon-o-plus')
                                    ->action(function (Repeater $component) {
                                        $state = $component->getState() ?? [];
                                        $state[(string) Str::uuid()] = [
                                            'name' => '',
                                            'quantity' => 0,
                                            'size' => null,
                                            'task_cost' => 0,
                                            'paper' => [
                                                (string) Str::uuid() => [
                                                    'inventory_item_id' => null,
                                                    'required_quantity' => 0,
                                                    'reserve_quantity' => 0,
                                                    'base_unit' => null,
                                                ],
                                            ],
                                        ];
                                        $component->state($state);
                                    }),
                            ])
                            ->minItems(1)
                            ->live() // Required for live total recalculation
                            ->afterStateUpdated(function (UtilitiesGet $get, UtilitiesSet $set) {
                                Calculations::sumRepeater($get, $set, 'jobOrderTasks', 'subtotal', 'task_cost');
                                Calculations::updateTaxedTotal($get, $set, 'subtotal', 'tax_amount', 'total');
                            })
                            ->deleteAction(
                                fn ($action) => $action->after(function (UtilitiesGet $get, UtilitiesSet $set) {
                                    Calculations::sumRepeater($get, $set, 'jobOrderTasks', 'subtotal', 'task_cost');
                                    $subtotal = (float) $get('subtotal');
                                    $taxRate = Setting::getSettings()->vat_enabled
                                        ? (float) Setting::getSettings()->vat_rate / 100
                                        : 0.0;
                                    $tax = round($subtotal * $taxRate, 2);
                                    $set('tax_amount', $tax);
                                    $set('total', $subtotal + $tax);
                                })
                            ),
                        Grid::make(2)
                            ->schema([
                                Section::make('Additional Services')
                                    ->schema([
                                        Grid::make(3)
                                            ->visible(fn (UtilitiesGet $get) => $get('job_type') === 'books')
                                            ->schema([
                                                Group::make([
                                                    Checkbox::make('services.typing')->label('Typing'),
                                                    Checkbox::make('services.layout_design')->label('Layout Design'),
                                                    Checkbox::make('services.cover_design')->label('Cover Design'),
                                                    Checkbox::make('services.selling_price')->label('Selling Price on Cover'),
                                                    Checkbox::make('services.spine_has_text')->label('Spine has text'),
                                                    Checkbox::make('services.cover_inner_printing')->label('Cover inner printing'),
                                                    Checkbox::make('services.dont_insert_printer_name')->label("Don't insert printer name"),
                                                    Checkbox::make('services.cover_proof')->label('Cover Proof'),
                                                    Checkbox::make('services.lamination')->label('Lamination'),
                                                ])->columns(1)->columnSpan(1),
                                                Group::make([
                                                    TextInput::make('services.page_no')->label('Number of Pages'),
                                                    TextInput::make('services.text_color_no')->label('Number of Colors (Text)'),
                                                    TextInput::make('services.cover_color_no')->label('Number of Colors (Cover)'),
                                                    TextInput::make('services.cover_ups')->label('Cover Ups'),
                                                    TextInput::make('services.books_per_package')->label('Books per Package'),
                                                    Select::make('services.binding_type')
                                                        ->label('Binding Type')
                                                        ->options([
                                                            'saddle' => 'Saddle stitch',
                                                            'perfect' => 'Perfect Binding',
                                                            'hardcover' => 'Hardcover',
                                                        ])
                                                        ->required(),
                                                ])->columnSpan(2)->columns(2),

                                            ]),
                                        Grid::make(3)
                                            ->visible(fn (UtilitiesGet $get) => $get('job_type') === 'packages')
                                            ->schema([
                                                Group::make([
                                                    Checkbox::make('services.new_design')->label('New Design'),
                                                    Checkbox::make('services.redesign')->label('Redesign'),
                                                    Checkbox::make('services.new_dielines')->label('New dielines'),
                                                    Checkbox::make('services.old_dielines')->label('Old dielines'),
                                                    Checkbox::make('services.full_color')->label('Full color'),
                                                    Checkbox::make('services.one_side_print')->label('One side print'),
                                                    Checkbox::make('services.back_side_print')->label('Back side print'),
                                                    Checkbox::make('services.work_and_turn')->label('Work and Turn'),
                                                ])->columnSpan(1)->columns(1),
                                                Group::make([
                                                    TextInput::make('services.amount_of_colors')->label('Amount of Colors'),
                                                    TextInput::make('services.printing_ups')->label('Printing Ups'),
                                                    TextInput::make('services.diecutting_ups')->label('Diecutting Ups'),
                                                    TextInput::make('services.pieces_per_sheet')->label('Peices per sheet'),
                                                    CheckboxList::make('services.colors_used')
                                                        ->options([
                                                            'C' => 'C',
                                                            'M' => 'M',
                                                            'Y' => 'Y',
                                                            'K' => 'K',
                                                        ])
                                                        ->label('Colors Used')
                                                        ->columns(4),
                                                    TextInput::make('services.panton_no1')->label('Panton No'),
                                                    TextInput::make('services.panton_no2')->label('Panton No'),
                                                    TextInput::make('services.panton_no3')->label('Panton No'),
                                                ])->columnSpan(2)->columns(2),
                                            ]),
                                        Grid::make(3)
                                            ->visible(fn (UtilitiesGet $get) => $get('job_type') === 'labels')
                                            ->schema([
                                                Group::make([
                                                    Checkbox::make('services.new_design')->label('New Design'),
                                                    Checkbox::make('services.redesign')->label('Redesign'),
                                                    Checkbox::make('services.new_dielines')->label('New dielines'),
                                                    Checkbox::make('services.old_dielines')->label('Old dielines'),
                                                    Checkbox::make('services.full_color')->label('Full color'),
                                                    Checkbox::make('services.one_side_print')->label('One side print'),
                                                    Checkbox::make('services.back_side_print')->label('Back side print'),
                                                    Checkbox::make('services.work_and_turn')->label('Work and Turn'),
                                                ])->columnSpan(1)->columns(1),
                                                Group::make([
                                                    TextInput::make('services.amount_of_colors')->label('Amount of Colors'),
                                                    TextInput::make('services.printing_ups')->label('Printing Ups'),
                                                    TextInput::make('services.diecutting_ups')->label('Diecutting Ups'),
                                                    TextInput::make('services.pieces_per_sheet')->label('Peices per sheet'),
                                                    CheckboxList::make('services.colors_used')
                                                        ->options([
                                                            'C' => 'C',
                                                            'M' => 'M',
                                                            'Y' => 'Y',
                                                            'K' => 'K',
                                                        ])
                                                        ->label('Colors Used')
                                                        ->columns(4),
                                                    TextInput::make('services.panton_no1')->label('Panton No'),
                                                    TextInput::make('services.panton_no2')->label('Panton No'),
                                                    TextInput::make('services.panton_no3')->label('Panton No'),
                                                ])->columnSpan(2)->columns(2),
                                            ]),
                                        Grid::make(3)
                                            ->visible(fn (UtilitiesGet $get) => $get('job_type') === 'vouchers')
                                            ->schema([
                                                Group::make([
                                                    Checkbox::make('services.new_design')->label('New Design'),
                                                    Checkbox::make('services.redesign')->label('Redesign'),
                                                    Checkbox::make('services.new_dielines')->label('New dielines'),
                                                    Checkbox::make('services.old_dielines')->label('Old dielines'),
                                                    Checkbox::make('services.full_color')->label('Full color'),
                                                    Checkbox::make('services.one_side_print')->label('One side print'),
                                                    Checkbox::make('services.back_side_print')->label('Back side print'),
                                                    Checkbox::make('services.work_and_turn')->label('Work and Turn'),
                                                ])->columnSpan(1)->columns(1),
                                                Group::make([
                                                    TextInput::make('services.amount_of_colors')->label('Amount of Colors'),
                                                    TextInput::make('services.printing_ups')->label('Printing Ups'),
                                                    TextInput::make('services.numbering_ups')->label('Numbering Ups'),
                                                    TextInput::make('services.pieces_per_sheet')->label('Peices per sheet'),
                                                    CheckboxList::make('services.colors_used')
                                                        ->options([
                                                            'C' => 'C',
                                                            'M' => 'M',
                                                            'Y' => 'Y',
                                                            'K' => 'K',
                                                        ])
                                                        ->label('Colors Used')
                                                        ->columns(4),
                                                    TextInput::make('services.panton_no1')->label('Panton No'),
                                                    TextInput::make('services.panton_no2')->label('Panton No'),
                                                    TextInput::make('services.panton_no3')->label('Panton No'),
                                                ])->columnSpan(2)->columns(2),
                                            ]),
                                    ])
                                    ->compact()
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpan(3),

                Section::make()
                    ->schema([
                        Hidden::make('status')->default('draft'),
                        Hidden::make('subtotal')
                            ->default(0)
                            ->dehydrated(),
                        Hidden::make('tax_amount')
                            ->default(0)
                            ->dehydrated(),
                        Hidden::make('total')
                            ->default(0)
                            ->dehydrated(),

                        Placeholder::make('summary_subtotal')
                            ->label('Subtotal')
                            ->content(fn (UtilitiesGet $get): HtmlString => self::summaryValue($get('subtotal')))
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->extraAttributes(['class' => 'cost-summary-metric']),

                        Placeholder::make('summary_tax_amount')
                            ->label('Tax (VAT)')
                            ->content(fn (UtilitiesGet $get): HtmlString => self::summaryValue($get('tax_amount')))
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->extraAttributes(['class' => 'cost-summary-metric']),

                        Placeholder::make('summary_total')
                            ->label('Total')
                            ->content(fn (UtilitiesGet $get): HtmlString => self::summaryValue($get('total'), isPrimary: true))
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->extraAttributes(['class' => 'cost-summary-metric cost-summary-total']),

                        Textarea::make('remarks'),
                    ])
                    ->extraAttributes(['class' => 'lg:sticky lg:top-6 orderSummary']),
            ])->columns(4);
    }

    private static function summaryValue(mixed $amount, bool $isPrimary = false): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="cost-summary-value%s">%s Birr</span>',
            $isPrimary ? ' cost-summary-value-primary' : '',
            e(Number::format((float) ($amount ?? 0), precision: 2)),
        ));
    }
}

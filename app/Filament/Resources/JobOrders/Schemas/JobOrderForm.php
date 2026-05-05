<?php

namespace App\Filament\Resources\JobOrders\Schemas;

use App\Filament\Support\PanelAccess;
use App\Support\PrivateStorage;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as UtilitiesGet;
use Filament\Schemas\Components\Utilities\Set as UtilitiesSet;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

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
                                Select::make('partner_id')
                                    ->label('Customer')
                                    ->relationship('partner', 'name', modifyQueryUsing: fn($query) => $query->where('is_customer', true))
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required(),
                                        TextInput::make('phone')
                                            ->required(),
                                        TextInput::make('address'),
                                        Hidden::make('is_customer')->default(true),
                                    ])
                                    ->preload()
                                    ->searchable()
                                    ->required(),
                                TextInput::make('job_order_number')
                                    ->label('Job Order #')
                                    ->default(function () {
                                        $lastJobOrder = \App\Models\JobOrder::orderBy('id', 'desc')->first();
                                        $lastNumber = 0;
                                        if ($lastJobOrder && preg_match('/JO-(\d+)/', $lastJobOrder->job_order_number, $matches)) {
                                            $lastNumber = (int) $matches[1];
                                        }
                                        return 'JO-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                                    })
                                    ->readOnly()
                                    ->required()
                            ]),
                        Grid::make()
                            ->schema([
                                Select::make('job_type')
                                    ->options([
                                        'books' => 'Books',
                                        'packages' => 'Packages',
                                        'vouchers' => 'Vouchers',
                                        'labels' => 'Labels'
                                    ])
                                    ->reactive()
                                    ->default('packages')
                                    ->required(),
                                Select::make('production_mode')
                                    ->options([
                                        'make_to_order' => 'Client Job',
                                        'make_to_stock' => 'Internal Job',
                                    ])
                                    ->default('make_to_order')
                                    ->required(),
                                DatePicker::make('submission_date')
                                    ->default(now())
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
                                    ->numeric(),

                                Select::make('size')
                                    ->label('Size')
                                    ->preload()
                                    ->relationship('sizeItem', 'size')
                                    ->createOptionForm([
                                        TextInput::make('size')
                                            ->required(),
                                    ])
                                    ->searchable()
                                    ->required(),

                                TextInput::make('task_cost')
                                    ->label('Cost')
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->required()
                                    ->live()
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
                                            ->options(\App\Models\InventoryItem::pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $item = \App\Models\InventoryItem::find($state);
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
                                    ->addable(false)
                                    ->reorderable(false)
                                    ->minItems(1)
                                    ->extraItemActions([
                                        Action::make('add_paper')
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
                                        $state[] = [
                                            'name' => '',
                                            'quantity' => 0,
                                            'size' => null,
                                            'task_cost' => 0,
                                            'paper' => [
                                                [
                                                    'inventory_item_id' => null,
                                                    'required_quantity' => 0,
                                                    'reserve_quantity' => 0,
                                                    'base_unit' => null,
                                                ]
                                            ],
                                        ];
                                        $component->state($state);
                                    }),
                            ])
                            ->minItems(1)
                            ->live() // Required for live total recalculation
                            ->afterStateUpdated(function (UtilitiesGet $get, UtilitiesSet $set) {
                                \App\Filament\Support\Calculations::sumRepeater($get, $set, 'jobOrderTasks', 'total_price', 'task_cost');
                            })
                            ->deleteAction(
                                fn($action) => $action->after(function (UtilitiesGet $get, UtilitiesSet $set) {
                                    \App\Filament\Support\Calculations::sumRepeater($get, $set, 'jobOrderTasks', 'total_price', 'task_cost');
                                })
                            ),
                        Grid::make(2)
                            ->schema([
                                Section::make('Additional Services')
                                    ->schema([
                                        Grid::make(3)
                                            ->visible(fn(UtilitiesGet $get) => $get('job_type') === 'books')
                                            ->schema([
                                                Group::make([
                                                    Checkbox::make('services.typing')->label('Typing'),
                                                    Checkbox::make('services.layout_design')->label('Layout Design'),
                                                    Checkbox::make('services.cover_design')->label('Cover Design'),
                                                    Checkbox::make('services.selling_price')->label('Selling Price on Cover'),
                                                    Checkbox::make('services.spine_has_text')->label('Spine has text'),
                                                    Checkbox::make('services.cover_inner_printing')->label('Cover inner printing'),
                                                    Checkbox::make('services.dont_insert_printer_name')->label("Don't insert printer name"),
                                                    Checkbox::make('services.cover_proof')->label("Cover Proof"),
                                                    Checkbox::make('services.lamination')->label("Lamination"),
                                                ])->columns(1)->columnSpan(1),
                                                Group::make([
                                                    TextInput::make('services.page_no')->label("Number of Pages"),
                                                    TextInput::make('services.text_color_no')->label("Number of Colors (Text)"),
                                                    TextInput::make('services.cover_color_no')->label("Number of Colors (Cover)"),
                                                    TextInput::make('services.cover_ups')->label("Cover Ups"),
                                                    TextInput::make('services.books_per_package')->label("Books per Package"),
                                                    Select::make('services.binding_type')
                                                        ->label("Binding Type")
                                                        ->options([
                                                            'saddle' => 'Saddle stitch',
                                                            'perfect' => 'Perfect Binding',
                                                            'hardcover' => 'Hardcover',
                                                        ])
                                                        ->required(),
                                                ])->columnSpan(2)->columns(2),

                                            ]),
                                        Grid::make(3)
                                            ->visible(fn(UtilitiesGet $get) => $get('job_type') === 'packages')
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
                                            ->visible(fn(UtilitiesGet $get) => $get('job_type') === 'labels')
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
                                            ->visible(fn(UtilitiesGet $get) => $get('job_type') === 'vouchers')
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
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->helperText('Status of the overall job order.')
                            ->required(),
                            
                        FileUpload::make('cost_calc_file')
                            ->label('Cost Calculation File')
                            ->disk('s3')
                            ->visibility('private')
                            ->getUploadedFileUsing(fn (\Filament\Forms\Components\BaseFileUpload $component, string $file, string | array | null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                            ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                            ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                            ->directory('job-orders/cost-calculations')
                            ->acceptedFileTypes(['application/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->maxSize(1024)
                            ->panelAspectRatio('3:1')
                            ->downloadable(fn ($record) => in_array(\Filament\Facades\Filament::getCurrentPanel()?->getId(), ['admin', 'finance', 'operations']))
                            ->dehydrated() // Add this line to make the file uploader work on edit pages
                            ->required(),
                        
                        DatePicker::make('due_date')
                            ->label('Payment Due Date')
                            ->default(fn() => now()->addDays(30))
                            ->helperText('Set the payment due date for this job order')
                            ->required(),
                        TextInput::make('total_price')
                            ->default(0)
                            ->suffix(' Birr')
                            ->readOnly()
                            ->numeric()
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->dehydratedWhenHidden(),

                        Textarea::make('remarks'),
                    ]),
            ])->columns(4);
    }
}

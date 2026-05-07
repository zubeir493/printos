<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Filament\Support\Calculations;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([

                        Grid::make(3)
                            ->schema([
                                TextInput::make('po_number')
                                    ->label('Purchase Order no.')
                                    ->default(function () {
                                        $lastPO = PurchaseOrder::orderBy('id', 'desc')->first();
                                        $lastNumber = 0;
                                        if ($lastPO && preg_match('/PO-(\d+)/', $lastPO->po_number, $matches)) {
                                            $lastNumber = (int) $matches[1];
                                        }

                                        return 'PO-'.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                                    })
                                    ->readOnly()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->dehydrated(),

                                Select::make('partner_id')
                                    ->label('Supplier')
                                    ->relationship('partner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_supplier', true))
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required(),
                                        TextInput::make('phone')
                                            ->required(),
                                        TextInput::make('tin_number'),
                                        Hidden::make('is_supplier')->default(true),
                                    ])
                                    ->required()
                                    ->dehydrated(),

                                DatePicker::make('order_date')
                                    ->default(now())
                                    ->required()
                                    ->dehydrated(),
                            ]),

                        Repeater::make('purchaseOrderItems')
                            ->relationship('purchaseOrderItems')
                            ->table([
                                TableColumn::make('Item')->width('200px')->alignLeft(),
                                TableColumn::make('Qty')->alignLeft(),
                                TableColumn::make('Unit Price')->alignLeft(),
                                TableColumn::make('Total')->alignLeft(),
                            ])
                            ->compact()
                            ->schema([
                                Select::make('inventory_item_id')
                                    ->relationship('inventoryItem', 'name', fn ($query) => $query->select('id', 'name', 'purchase_unit'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $unit = InventoryItem::find($state)?->purchase_unit ?? '';
                                        $set('unit_label', $unit);
                                    })
                                    ->dehydrated(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->live()
                                    ->suffix(fn ($get) => $get('unit_label') ?? '')
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('total', (float) ($state ?? 0) * (float) ($get('unit_price') ?? 0));
                                        Calculations::updateSubtotal($get, $set, '../../purchaseOrderItems', '../../subtotal');
                                        $subtotal = (float) $get('../../subtotal');
                                        $taxRate = Setting::getSettings()->vat_enabled
                                            ? (float) Setting::getSettings()->vat_rate / 100
                                            : 0.0;
                                        $tax = round($subtotal * $taxRate, 2);
                                        $set('../../tax_amount', $tax);
                                        $set('../../total', $subtotal + $tax);
                                    })
                                    ->dehydrated(),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->suffix('Birr')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('total', (float) ($state ?? 0) * (float) ($get('quantity') ?? 0));
                                        Calculations::updateSubtotal($get, $set, '../../purchaseOrderItems', '../../subtotal');
                                        $subtotal = (float) $get('../../subtotal');
                                        $taxRate = Setting::getSettings()->vat_enabled
                                            ? (float) Setting::getSettings()->vat_rate / 100
                                            : 0.0;
                                        $tax = round($subtotal * $taxRate, 2);
                                        $set('../../tax_amount', $tax);
                                        $set('../../total', $subtotal + $tax);
                                    })
                                    ->dehydrated(),

                                TextInput::make('total')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->suffix('Birr'),
                            ])
                            ->columns(5)
                            ->live()
                            ->afterStateUpdated(function ($get, $set) {
                                Calculations::updateSubtotal($get, $set, 'purchaseOrderItems', 'subtotal');
                                $subtotal = (float) $get('subtotal');
                                $taxRate = Setting::getSettings()->vat_enabled
                                    ? (float) Setting::getSettings()->vat_rate / 100
                                    : 0.0;
                                $tax = round($subtotal * $taxRate, 2);
                                $set('tax_amount', $tax);
                                $set('total', $subtotal + $tax);
                            })
                            ->deleteAction(
                                fn ($action) => $action->after(function ($get, $set) {
                                    Calculations::updateSubtotal($get, $set, 'purchaseOrderItems', 'subtotal');
                                    $subtotal = (float) $get('subtotal');
                                    $taxRate = Setting::getSettings()->vat_enabled
                                        ? (float) Setting::getSettings()->vat_rate / 100
                                        : 0.0;
                                    $tax = round($subtotal * $taxRate, 2);
                                    $set('tax_amount', $tax);
                                    $set('total', $subtotal + $tax);
                                })
                            )
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addable(false)
                            ->extraItemActions([
                                Action::make('add_item')
                                    ->label('Add Item')
                                    ->icon('heroicon-o-plus')
                                    ->action(function (Repeater $component) {
                                        $state = $component->getState() ?? [];
                                        $state[] = [
                                            'inventory_item_id' => null,
                                            'quantity' => 0,
                                            'unit_price' => 0,
                                            'total' => 0,
                                        ];
                                        $component->state($state);
                                    }),
                            ]),

                    ])->columnSpan(3),
                Section::make()
                    ->compact()
                    ->schema([
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'approved' => 'Approved',
                                'received' => 'Received',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),

                        DatePicker::make('due_date')
                            ->label('Payment Due Date')
                            ->default(fn () => now()->addDays(30))
                            ->helperText('Set the payment due date for this job order')
                            ->required(),
                        TextInput::make('subtotal')
                            ->numeric()
                            ->suffix('Birr')
                            ->readOnly()
                            ->default(0)
                            ->dehydrated(),

                        TextInput::make('tax_amount')
                            ->label('Tax (VAT)')
                            ->numeric()
                            ->suffix('Birr')
                            ->readOnly()
                            ->default(0)
                            ->dehydrated(),

                        TextInput::make('total')
                            ->label('Total')
                            ->numeric()
                            ->suffix('Birr')
                            ->readOnly()
                            ->default(0)
                            ->dehydrated(),

                    ]),
            ])->columns(4);
    }
}

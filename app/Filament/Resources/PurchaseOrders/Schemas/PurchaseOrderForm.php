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
                                        TextInput::make('email')
                                            ->email(),
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
                                TableColumn::make('Item')->width('250px')->alignLeft(),
                                TableColumn::make('Qty')->width('160px')->alignLeft(),
                                TableColumn::make('Unit Price')->width('160px')->alignLeft(),
                                TableColumn::make('Total')->width('160px')->alignLeft(),
                            ])
                            ->compact()
                            ->schema([
                                Select::make('inventory_item_id')
                                    ->relationship('inventoryItem', 'name', fn ($query) => $query->select('id', 'name', 'purchase_unit', 'unit', 'average_cost', 'price', 'conversion_factor'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->getOptionLabelUsing(fn ($value) => \Illuminate\Support\Str::limit(
                                        \App\Models\InventoryItem::find($value)?->name ?? '',
                                        30
                                    ))
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $item = InventoryItem::find($state);
                                        if (! $item) {
                                            return;
                                        }
                                        $set('unit_label', $item->hasPurchaseUnit() ? $item->purchase_unit : ($item->unit ?? 'unit'));
                                        $set('unit_price', $item->pricePerPurchaseUnit());
                                    })
                                    ->dehydrated(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->live()
                                    ->suffix(fn ($get) => $get('unit_label') ?: 'unit')
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

                                Hidden::make('unit_label')
                                    ->default('unit')
                                    ->afterStateHydrated(function ($set, $get) {
                                        $itemId = $get('inventory_item_id');
                                        if (! $itemId) {
                                            return;
                                        }
                                        $item = InventoryItem::find($itemId);
                                        if ($item) {
                                            $set('unit_label', $item->hasPurchaseUnit() ? $item->purchase_unit : ($item->unit ?? 'unit'));
                                        }
                                    }),
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
                                            'unit_label' => 'unit',
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

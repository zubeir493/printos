<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Filament\Support\Calculations;
use App\Models\InventoryItem;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

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

                                DatePicker::make('due_date')
                                    ->label('Payment Due Date')
                                    ->default(fn () => now()->addDays(30))
                                    ->required(),
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
                                    ->getOptionLabelUsing(fn ($value) => Str::limit(
                                        InventoryItem::find($value)?->name ?? '',
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
                                    ->default(1)
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
                                    ->suffix('Birr')
                                    ->afterStateHydrated(function ($set, $get) {
                                        $set('total', round((float) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0), 2));
                                        Calculations::updateSubtotal($get, $set, '../../purchaseOrderItems', '../../subtotal');
                                        Calculations::updateTaxedTotal($get, $set, '../../subtotal', '../../tax_amount', '../../total');
                                    }),

                                Hidden::make('unit_label')
                                    ->default('unit')
                                    ->dehydrated(false)
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
                                Calculations::updateTaxedTotal($get, $set, 'subtotal', 'tax_amount', 'total');
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
                                        $state[(string) Str::uuid()] = [
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
                        Hidden::make('status')
                            ->default('draft'),
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
                            ->content(fn ($get): HtmlString => self::summaryValue($get('subtotal')))
                            ->extraAttributes(['class' => 'cost-summary-metric']),

                        Placeholder::make('summary_tax_amount')
                            ->label('Tax (VAT)')
                            ->content(fn ($get): HtmlString => self::summaryValue($get('tax_amount')))
                            ->extraAttributes(['class' => 'cost-summary-metric']),

                        Placeholder::make('summary_total')
                            ->label('Total')
                            ->content(fn ($get): HtmlString => self::summaryValue($get('total'), isPrimary: true))
                            ->extraAttributes(['class' => 'cost-summary-metric cost-summary-total']),

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

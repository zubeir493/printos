<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Filament\Support\Calculations;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\SalesOrderItemImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('order_number')
                                    ->label('Sales Order #')
                                    ->default(function () {
                                        $lastOrder = SalesOrder::orderBy('id', 'desc')->first();
                                        $lastNumber = 0;

                                        if ($lastOrder && preg_match('/SO-(\d+)/', $lastOrder->order_number, $matches)) {
                                            $lastNumber = (int) $matches[1];
                                        }

                                        return 'SO-'.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                                    })
                                    ->readOnly()
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                Select::make('partner_id')
                                    ->label('Customer')
                                    ->relationship('partner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_customer', true))
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => Partner::where('id', 1)->first()?->id)
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                        TextInput::make('phone'),
                                        TextInput::make('email')->email(),
                                        TextInput::make('address'),
                                        Hidden::make('is_customer')->default(true),
                                    ]),
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->relationship('warehouse', 'name')
                                    ->searchable()
                                    ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                                    ->preload()
                                    ->required(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('order_date')
                                    ->default(now())
                                    ->required(),
                                Select::make('payment_mode')
                                    ->label('Payment Type')
                                    ->options([
                                        'cash' => 'Cash',
                                        'credit' => 'Credit',
                                    ])
                                    ->default('cash')
                                    ->required()
                                    ->live(),
                                // Standalone import button — FileUpload lives inside the action modal,
                                // completely isolated from the form's save lifecycle.
                                SchemaActions::make([
                                    Action::make('import_items')
                                        ->label('Import from CSV / Excel')
                                        ->icon('heroicon-o-arrow-up-tray')
                                        ->color(Color::Indigo)
                                        ->visible(fn () => ! request()->routeIs('*.view'))
                                        ->modalHeading('Import Sale Items')
                                        ->modalDescription('Upload a CSV or Excel file. Required columns: name (or sku / inventory_item_id), quantity. Optional: unit_price.')
                                        ->modalWidth('lg')
                                        ->schema([
                                            FileUpload::make('import_file')
                                                ->label('File')
                                                ->acceptedFileTypes([
                                                    'text/csv',
                                                    'application/csv',
                                                    'application/vnd.ms-excel',
                                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                                ])
                                                ->required(),
                                        ])
                                        ->action(function (array $data, Get $get, Set $set) {
                                            $file = $data['import_file'];
                                            $path = is_array($file) ? array_key_first($file) : $file;

                                            try {
                                                // $path already contains the full relative path (e.g. livewire-tmp/xxxx.csv)
                                                $disk = FileUploadConfiguration::disk();
                                                $contents = Storage::disk($disk)->get($path);

                                                if ($contents === null) {
                                                    throw new \RuntimeException('Could not read the uploaded file. Please try again.');
                                                }

                                                // Write to a real OS temp file so OpenSpout can open it by path
                                                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'csv';
                                                $localTmp = tempnam(sys_get_temp_dir(), 'so_import_').'.'.$ext;
                                                file_put_contents($localTmp, $contents);

                                                try {
                                                    $imported = app(SalesOrderItemImportService::class)->importRows($localTmp);
                                                } finally {
                                                    @unlink($localTmp);
                                                }

                                                // Merge with existing rows:
                                                // - Same item + same price → sum quantities
                                                // - Same item + different price → keep as separate row (user resolves)
                                                // - New item → append
                                                $existing = collect($get('salesOrderItems') ?? []);

                                                foreach ($imported as $newRow) {
                                                    $matchIndex = $existing->search(fn ($row) => (int) ($row['inventory_item_id'] ?? 0) === (int) ($newRow['inventory_item_id'] ?? 0)
                                                        && (float) ($row['unit_price'] ?? 0) === (float) ($newRow['unit_price'] ?? 0)
                                                    );

                                                    if ($matchIndex !== false) {
                                                        // Same item, same price — merge quantities
                                                        $merged = $existing[$matchIndex];
                                                        $merged['quantity'] = (float) $merged['quantity'] + (float) $newRow['quantity'];
                                                        $merged['total'] = round($merged['quantity'] * (float) $merged['unit_price'], 2);
                                                        $existing[$matchIndex] = $merged;
                                                    } else {
                                                        // New item or same item with different price — append
                                                        $existing->push($newRow);
                                                    }
                                                }

                                                $rows = $existing->values()->toArray();
                                                $set('salesOrderItems', $rows);

                                                $subtotal = collect($rows)->sum('total');
                                                $taxRate = Setting::getSettings()->vat_enabled
                                                    ? (float) Setting::getSettings()->vat_rate / 100
                                                    : 0.0;
                                                $tax = round($subtotal * $taxRate, 2);
                                                $set('subtotal', $subtotal);
                                                $set('tax_amount', $tax);
                                                $set('total', $subtotal + $tax);

                                                $mergedCount = count($imported) - collect($imported)->filter(fn ($r) => collect($get('salesOrderItems') ?? [])->contains(fn ($e) => (int) ($e['inventory_item_id'] ?? 0) === (int) ($r['inventory_item_id'] ?? 0)
                                                )
                                                )->count();

                                                Notification::make()
                                                    ->title(count($imported).' item(s) imported')
                                                    ->body('Matching items with the same price had their quantities merged. Items with different prices were added as separate rows.')
                                                    ->success()
                                                    ->send();
                                            } catch (\Throwable $e) {
                                                Notification::make()
                                                    ->title('Import failed')
                                                    ->body($e->getMessage())
                                                    ->danger()
                                                    ->persistent()
                                                    ->send();
                                            }
                                        }),
                                ])->label('Bulk Import'),
                            ]),
                        Repeater::make('salesOrderItems')
                            ->relationship('salesOrderItems')
                            ->label('Sale Items')
                            ->table([
                                TableColumn::make('Item')->width('220px')->alignLeft(),
                                TableColumn::make('Qty')->alignLeft(),
                                TableColumn::make('Unit Price')->alignLeft(),
                                TableColumn::make('Total')->alignLeft(),
                            ])
                            ->compact()
                            ->schema([
                                Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->relationship('inventoryItem', 'name', fn ($query) => $query->where('is_sellable', true))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $item = InventoryItem::find($state);
                                        if (! $item) {
                                            return;
                                        }
                                        $unit = $item->hasPurchaseUnit() ? $item->purchase_unit : ($item->unit ?? 'unit');
                                        $price = (float) ($item->price ?? 0);
                                        $qty = (float) ($get('quantity') ?? 1);

                                        $set('unit_label', $unit);
                                        $set('unit_price', $price);
                                        $set('total', round($qty * $price, 2));
                                    }),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->suffix(fn ($get) => $get('unit_label') ?: 'unit')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $set('total', round((float) ($state ?? 0) * (float) ($get('unit_price') ?? 0), 2));
                                        Calculations::updateSubtotal($get, $set, '../../salesOrderItems', '../../subtotal');
                                        $subtotal = (float) $get('../../subtotal');
                                        $taxRate = Setting::getSettings()->vat_enabled
                                            ? (float) Setting::getSettings()->vat_rate / 100
                                            : 0.0;
                                        $tax = round($subtotal * $taxRate, 2);
                                        $set('../../tax_amount', $tax);
                                        $set('../../total', $subtotal + $tax);
                                    }),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->suffix('Birr')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $set('total', round((float) ($state ?? 0) * (float) ($get('quantity') ?? 0), 2));
                                        Calculations::updateSubtotal($get, $set, '../../salesOrderItems', '../../subtotal');
                                        $subtotal = (float) $get('../../subtotal');
                                        $taxRate = Setting::getSettings()->vat_enabled
                                            ? (float) Setting::getSettings()->vat_rate / 100
                                            : 0.0;
                                        $tax = round($subtotal * $taxRate, 2);
                                        $set('../../tax_amount', $tax);
                                        $set('../../total', $subtotal + $tax);
                                    }),
                                TextInput::make('total')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->suffix('Birr')
                                    ->afterStateHydrated(function (Set $set, Get $get) {
                                        $set('total', round((float) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0), 2));
                                        Calculations::updateSubtotal($get, $set, '../../salesOrderItems', '../../subtotal');
                                        Calculations::updateTaxedTotal($get, $set, '../../subtotal', '../../tax_amount', '../../total');
                                    }),
                                Hidden::make('unit_label')
                                    ->default('unit')
                                    ->dehydrated()
                                    ->afterStateHydrated(function ($set, $get) {
                                        $itemId = $get('inventory_item_id');
                                        if (! $itemId) {
                                            return;
                                        }
                                        $item = InventoryItem::find($itemId);
                                        if ($item && ! $get('unit_label')) {
                                            $set('unit_label', $item->hasPurchaseUnit() ? $item->purchase_unit : ($item->unit ?? 'unit'));
                                        }
                                    }),
                            ])
                            ->columns(4)
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
                                            'quantity' => 1,
                                            'unit_price' => 0,
                                            'total' => 0,
                                            'unit_label' => 'unit',
                                        ];
                                        $component->state($state);
                                    }),
                            ])
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                Calculations::updateSubtotal($get, $set, 'salesOrderItems', 'subtotal');
                                Calculations::updateTaxedTotal($get, $set, 'subtotal', 'tax_amount', 'total');
                            })
                            ->deleteAction(
                                fn ($action) => $action->after(function (Get $get, Set $set) {
                                    Calculations::updateSubtotal($get, $set, 'salesOrderItems', 'subtotal');
                                    $subtotal = (float) $get('subtotal');
                                    $taxRate = Setting::getSettings()->vat_enabled
                                        ? (float) Setting::getSettings()->vat_rate / 100
                                        : 0.0;
                                    $tax = round($subtotal * $taxRate, 2);
                                    $set('tax_amount', $tax);
                                    $set('total', $subtotal + $tax);
                                })
                            ),
                    ])
                    ->columnSpan(3),
                Section::make('Summary')
                    ->schema([
                        DatePicker::make('due_date')
                            ->label('Payment Due Date')
                            ->live()
                            ->default(now())
                            ->required(),

                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->readOnly()
                            ->numeric()
                            ->default(0)
                            ->suffix('Birr')
                            ->dehydrated(),

                        TextInput::make('tax_amount')
                            ->label('Tax (VAT)')
                            ->readOnly()
                            ->numeric()
                            ->default(0)
                            ->suffix('Birr')
                            ->dehydrated(),

                        TextInput::make('total')
                            ->label('Total')
                            ->readOnly()
                            ->numeric()
                            ->default(0)
                            ->suffix('Birr')
                            ->dehydrated(),
                    ]),
            ])
            ->columns(4);
    }
}

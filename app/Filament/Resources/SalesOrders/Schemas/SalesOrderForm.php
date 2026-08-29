<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Filament\Support\Calculations;
use App\Models\Bank;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\SalesOrderItemImportService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
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
                                Select::make('partner_id')
                                    ->label('Customer')
                                    ->relationship('partner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_customer', true))
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => Partner::query()
                                        ->where('is_customer', true)
                                        ->where('name', 'Walk-In Customer')
                                        ->first()?->id
                                        ?? Partner::query()
                                            ->where('is_customer', true)
                                            ->orderBy('id')
                                            ->first()?->id)
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
                                DatePicker::make('order_date')
                                    ->default(now())
                                    ->required(),
                                Select::make('payment_mode')
                                    ->label('Settlement Type')
                                    ->options([
                                        'cash' => 'Paid now',
                                        'credit' => 'Credit',
                                    ])
                                    ->default('cash')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        if ($state !== 'credit') {
                                            $set('has_initial_payment', false);
                                        }
                                    }),
                                Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'bank' => 'Bank Transfer',
                                        'cheque' => 'Cheque',
                                    ])
                                    ->default('cash')
                                    ->visible(fn (Get $get): bool => $get('payment_mode') === 'cash')
                                    ->required(fn (Get $get): bool => $get('payment_mode') === 'cash')
                                    ->dehydrated(fn (Get $get): bool => $get('payment_mode') === 'cash')
                                    ->live(),
                                Select::make('bank_id')
                                    ->label('Bank Account')
                                    ->relationship('bank', 'name', modifyQueryUsing: fn ($query) => $query->where('status', 'active'))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get): bool => $get('payment_mode') === 'cash'
                                        && in_array($get('payment_method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                                    ->required(fn (Get $get): bool => $get('payment_mode') === 'cash'
                                        && in_array($get('payment_method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                                    ->dehydrated(fn (Get $get): bool => $get('payment_mode') === 'cash'
                                        && in_array($get('payment_method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
                                TextInput::make('payment_reference')
                                    ->label('Payment Reference')
                                    ->placeholder('Transfer reference, cheque number, or short note')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('payment_mode') === 'cash')
                                    ->dehydrated(fn (Get $get): bool => $get('payment_mode') === 'cash'),
                                DatePicker::make('due_date')
                                    ->label('Payment Due Date')
                                    ->live()
                                    ->default(now())
                                    ->visible(fn (Get $get): bool => $get('payment_mode') === 'credit')
                                    ->required(fn (Get $get): bool => $get('payment_mode') === 'credit')
                                    ->dehydrated(fn (Get $get): bool => $get('payment_mode') === 'credit'),
                                Toggle::make('has_initial_payment')
                                    ->label('Record Initial Payment')
                                    ->helperText('Enter a payment received with this credit order.')
                                    ->default(false)
                                    ->visible(fn (Get $get): bool => $get('payment_mode') === 'credit')
                                    ->dehydrated(fn (Get $get): bool => $get('payment_mode') === 'credit')
                                    ->live(),
                                // Standalone import button — FileUpload lives inside the action modal,
                                // completely isolated from the form's save lifecycle.
                                SchemaActions::make([
                                    Action::make('import_items')
                                        ->label('Import items from CSV / Excel')
                                        ->icon('heroicon-o-arrow-up-tray')
                                        ->color('gray')
                                        ->visible(fn () => ! request()->routeIs('*.view'))
                                        ->modalHeading('Import Sale Items')
                                        ->modalDescription(new HtmlString(
                                            'Use the <a href="'.asset('import-templates/sales-order-items.csv').'" download class="font-medium text-primary-600 hover:underline dark:text-primary-400">example CSV template</a> to see the supported columns.'
                                        ))
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
                                                $rows = self::mergeImportedRows($get('salesOrderItems') ?? [], $imported);

                                                $set('salesOrderItems', $rows);

                                                $subtotal = collect($rows)->sum('total');
                                                $taxRate = Setting::getSettings()->vat_enabled
                                                    ? (float) Setting::getSettings()->vat_rate / 100
                                                    : 0.0;
                                                $tax = round($subtotal * $taxRate, 2);
                                                $set('subtotal', $subtotal);
                                                $set('tax_amount', $tax);
                                                $set('total', $subtotal + $tax);

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
                        Section::make('Initial Payment')
                            ->description('Optional payment received now. It will be recorded when you save this credit order.')
                            ->visible(fn (Get $get): bool => $get('payment_mode') === 'credit'
                                && (bool) $get('has_initial_payment'))
                            ->schema([
                                TextInput::make('initial_payment_amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn (Get $get): float => (float) ($get('total') ?? 0))
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->dehydrated(fn (Get $get): bool => (bool) $get('has_initial_payment'))
                                    ->suffix(fn (): string => Money::suffix())
                                    ->helperText(fn (Get $get): string => 'Order total: '.self::summaryValueText($get('total'))),
                                Select::make('initial_payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'bank' => 'Bank Transfer',
                                        'cheque' => 'Cheque',
                                    ])
                                    ->default('cash')
                                    ->required(fn (Get $get): bool => (bool) $get('has_initial_payment')
                                        && (float) ($get('initial_payment_amount') ?? 0) > 0)
                                    ->dehydrated(fn (Get $get): bool => (bool) $get('has_initial_payment')
                                        && (float) ($get('initial_payment_amount') ?? 0) > 0)
                                    ->live(),
                                Select::make('initial_payment_bank_id')
                                    ->label('Bank Account')
                                    ->options(fn (): array => Bank::query()
                                        ->where('status', 'active')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get): bool => (bool) $get('has_initial_payment')
                                        && in_array($get('initial_payment_method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                                    ->required(fn (Get $get): bool => (bool) $get('has_initial_payment')
                                        && (float) ($get('initial_payment_amount') ?? 0) > 0
                                        && in_array($get('initial_payment_method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                                    ->dehydrated(fn (Get $get): bool => (bool) $get('has_initial_payment')
                                        && (float) ($get('initial_payment_amount') ?? 0) > 0
                                        && in_array($get('initial_payment_method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
                                TextInput::make('initial_payment_reference')
                                    ->label('Payment Reference')
                                    ->placeholder('Receipt number, transfer reference, or short note')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => (bool) $get('has_initial_payment')
                                        && in_array($get('initial_payment_method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                                    ->dehydrated(fn (Get $get): bool => (bool) $get('has_initial_payment')
                                        && in_array($get('initial_payment_method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
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

                                        // Selecting an item updates the row values programmatically, so
                                        // recalculate the form-level totals here as well as on manual edits.
                                        Calculations::updateSubtotal($get, $set, '../../salesOrderItems', '../../subtotal');
                                        Calculations::updateTaxedTotal($get, $set, '../../subtotal', '../../tax_amount', '../../total');
                                    }),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(0.01)
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
                                    ->minValue(0)
                                    ->suffix(fn (): string => Money::suffix())
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
                                    ->suffix(fn (): string => Money::suffix())
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
                                        $state[(string) Str::uuid()] = [
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
                Section::make()
                    ->schema([
                        Hidden::make('subtotal')
                            ->default(0)
                            ->live()
                            ->dehydrated(),
                        Hidden::make('tax_amount')
                            ->default(0)
                            ->live()
                            ->dehydrated(),
                        Hidden::make('total')
                            ->default(0)
                            ->live()
                            ->dehydrated(),

                        Placeholder::make('summary_subtotal')
                            ->label('Subtotal')
                            ->content(fn (Get $get): HtmlString => self::summaryValue($get('subtotal')))
                            ->extraAttributes(['class' => 'cost-summary-metric']),

                        Placeholder::make('summary_tax_amount')
                            ->label('Tax (VAT)')
                            ->content(fn (Get $get): HtmlString => self::summaryValue($get('tax_amount')))
                            ->extraAttributes(['class' => 'cost-summary-metric']),

                        Placeholder::make('summary_total')
                            ->label('Total')
                            ->content(fn (Get $get): HtmlString => self::summaryValue($get('total'), isPrimary: true))
                            ->extraAttributes(['class' => 'cost-summary-metric cost-summary-total']),
                    ])
                    ->extraAttributes(['class' => 'lg:sticky lg:top-6 orderSummary']),
            ])
            ->columns(4);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $existingRows
     * @param  array<int|string, array<string, mixed>>  $importedRows
     * @return array<int, array<string, mixed>>
     */
    public static function mergeImportedRows(array $existingRows, array $importedRows): array
    {
        $rows = collect($existingRows)
            ->filter(fn ($row): bool => is_array($row) && filled($row['inventory_item_id'] ?? null))
            ->values();

        foreach ($importedRows as $newRow) {
            $matchIndex = $rows->search(
                fn ($row): bool => (int) ($row['inventory_item_id'] ?? 0) === (int) ($newRow['inventory_item_id'] ?? 0)
                && (float) ($row['unit_price'] ?? 0) === (float) ($newRow['unit_price'] ?? 0)
            );

            if ($matchIndex !== false) {
                $merged = $rows[$matchIndex];
                $merged['quantity'] = (float) $merged['quantity'] + (float) $newRow['quantity'];
                $merged['total'] = round($merged['quantity'] * (float) $merged['unit_price'], 2);
                $rows[$matchIndex] = $merged;

                continue;
            }

            $rows->push($newRow);
        }

        return $rows->values()->toArray();
    }

    private static function summaryValue(mixed $amount, bool $isPrimary = false): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="cost-summary-value%s">%s %s</span>',
            $isPrimary ? ' cost-summary-value-primary' : '',
            e(Number::format((float) ($amount ?? 0), precision: 2)),
            e(Money::suffix()),
        ));
    }

    private static function summaryValueText(mixed $amount): string
    {
        return Number::format((float) ($amount ?? 0), precision: 2).' '.Money::suffix();
    }
}

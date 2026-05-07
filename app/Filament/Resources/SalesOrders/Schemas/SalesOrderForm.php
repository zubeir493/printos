<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Filament\Support\Calculations;
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
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                                Toggle::make('use_file_import')
                                    ->label('Import from Excel/CSV')
                                    ->helperText('Toggle to use file import instead of manual entry')
                                    ->live(),
                                FileUpload::make('items_import_file')
                                    ->label('Import Sales Items')
                                    ->acceptedFileTypes([
                                        'text/csv',
                                        'application/csv',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    ])
                                    ->visible(fn (Get $get) => $get('use_file_import'))
                                    ->dehydrated(false)
                                    ->helperText('Headers supported: inventory_item_id or sku or name, plus quantity and unit_price.')
                                    ->afterStateUpdated(function (Set $set, Get $get, TemporaryUploadedFile|string|null $state) {
                                        if (! $state instanceof TemporaryUploadedFile) {
                                            return;
                                        }

                                        try {
                                            $rows = app(SalesOrderItemImportService::class)->importRows($state->getRealPath());

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
                                                ->title(count($rows).' sales item(s) imported')
                                                ->success()
                                                ->send();
                                        } catch (\Throwable $exception) {
                                            Notification::make()
                                                ->title('Unable to import sales items')
                                                ->body($exception->getMessage())
                                                ->danger()
                                                ->persistent()
                                                ->send();
                                        }
                                    }),
                            ]),
                        Repeater::make('salesOrderItems')
                            ->relationship('salesOrderItems')
                            ->label('Sale Items')
                            ->visible(fn (Get $get) => ! $get('use_file_import'))
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
                                    ->required(),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
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
                                    ->suffix('Birr'),
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
                                        ];
                                        $component->state($state);
                                    }),
                            ])
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                Calculations::updateSubtotal($get, $set, 'salesOrderItems', 'subtotal');
                                $subtotal = (float) $get('subtotal');
                                $taxRate = Setting::getSettings()->vat_enabled
                                    ? (float) Setting::getSettings()->vat_rate / 100
                                    : 0.0;
                                $tax = round($subtotal * $taxRate, 2);
                                $set('tax_amount', $tax);
                                $set('total', $subtotal + $tax);
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
                        // Repeater::make('payments')
                        //     ->label('Payment Methods')
                        //     ->table([
                        //         TableColumn::make('Method'),
                        //         TableColumn::make('Amount'),
                        //         TableColumn::make('Reference'),
                        //     ])
                        //     ->compact()
                        //     ->schema([
                        //         Select::make('method')
                        //             ->label('Method')
                        //             ->options([
                        //                 'cash' => 'Cash',
                        //                 'bank' => 'Bank Transfer',
                        //                 'cheque' => 'Cheque',
                        //             ])
                        //             ->required()
                        //             ->live()
                        //             ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        //                 // Clear bank_id when method changes from bank
                        //                 if ($state !== 'bank') {
                        //                     $set('bank_id', null);
                        //                 }
                        //             }),
                        //         Select::make('bank_id')
                        //             ->label('Bank Account')
                        //             ->relationship('bank', 'name')
                        //             ->searchable()
                        //             ->preload()
                        //             ->visible(fn(Get $get) => $get('method') === 'bank')
                        //             ->required(fn(Get $get) => $get('method') === 'bank')
                        //             ->helperText('Select bank account for this payment'),
                        //         TextInput::make('amount')
                        //             ->label('Amount')
                        //             ->numeric()
                        //             ->prefix('₱')
                        //             ->step(0.01)
                        //             ->required()
                        //             ->rules(['min:0.01'])
                        //             ->live(onBlur: true)
                        //             ->afterStateUpdated(function (Set $set, Get $get) {
                        //                 // Recalculate total paid and balance
                        //                 $payments = $get('../../payments') ?? [];
                        //                 $totalPaid = collect($payments)->sum('amount');
                        //                 $orderTotal = $get('../../total') ?? 0;

                        //                 $set('../../total_paid_display', $totalPaid);
                        //                 $set('../../balance_display', max(0, $orderTotal - $totalPaid));
                        //             }),
                        //         TextInput::make('reference')
                        //             ->label('Reference')
                        //             ->placeholder('Receipt number, cheque number, etc.')
                        //             ->maxLength(255),
                        //     ])
                        //     ->columns(2)
                        //     ->defaultItems(1)
                        //     ->minItems(1)
                        //     ->visible(fn(Get $get) => $get('payment_mode') === 'cash' && request()->routeIs('filament.admin.resources.sales-orders.create'))
                        //     ->live()
                        //     ->afterStateUpdated(function (Get $get, Set $set) {
                        //         // Recalculate totals when payments change
                        //         $payments = $get('payments') ?? [];
                        //         $totalPaid = collect($payments)->sum('amount');
                        //         $orderTotal = $get('total') ?? 0;

                        //         $set('total_paid_display', $totalPaid);
                        //         $set('balance_display', max(0, $orderTotal - $totalPaid));
                        //     })
                        //     ->addable()
                        //     ->visible(fn(Get $get) => $get('payment_mode') === 'cash' && request()->routeIs('filament.admin.resources.sales-orders.create'))
                        //     ->deletable(),
                    ])
                    ->columnSpan(3),
                Section::make('Summary')
                    ->schema([
                        // Payment Info
                        Select::make('payment_mode')
                            ->label('Payment Type')
                            ->options([
                                'cash' => 'Cash Sale',
                                'credit' => 'Credit Sale',
                            ])
                            ->live()
                            ->required(),

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

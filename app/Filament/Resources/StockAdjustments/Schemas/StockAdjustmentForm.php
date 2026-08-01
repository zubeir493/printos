<?php

namespace App\Filament\Resources\StockAdjustments\Schemas;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\StockAdjustmentItemImportService;
use App\Support\StockTransferQuantity;
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
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

class StockAdjustmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ComponentsSection::make()
                    ->schema([
                        TextInput::make('adjustment_number')
                            ->label('Adjustment Number')
                            ->default(function () {
                                $lastAdjustment = StockAdjustment::orderBy('id', 'desc')->first();
                                $lastNumber = 0;
                                if ($lastAdjustment && preg_match('/ADJ-(\d+)/', $lastAdjustment->adjustment_number, $matches)) {
                                    $lastNumber = (int) $matches[1];
                                }

                                return 'ADJ-'.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                            })
                            ->readOnly()
                            ->dehydrated(false),
                        Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('items', []))
                            ->disabled(fn ($record) => $record?->status === 'posted'),
                        DatePicker::make('adjustment_date')
                            ->default(now())
                            ->required()
                            ->disabled(fn ($record) => $record?->status === 'posted'),
                        TextInput::make('reason')
                            ->disabled(fn ($record) => $record?->status === 'posted')
                            ->required(),
                        Hidden::make('status')
                            ->default('draft'),
                        SchemaActions::make([
                            Action::make('import_items')
                                ->label('Import from CSV / Excel')
                                ->icon('heroicon-o-arrow-up-tray')
                                ->color('gray')
                                ->visible(fn ($record) => ! request()->routeIs('*.view') && $record?->status !== 'posted')
                                ->modalHeading('Import Adjustment Items')
                                ->modalDescription(new HtmlString(
                                    'Use the <a href="'.asset('import-templates/stock-adjustment-items.csv').'" download class="font-medium text-primary-600 hover:underline dark:text-primary-400">example CSV template</a> to see the supported columns.'
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
                                ->action(function (array $data, Get $get, Set $set): void {
                                    $warehouseId = $get('warehouse_id');

                                    if (! $warehouseId) {
                                        Notification::make()
                                            ->title('Select a warehouse first')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $file = $data['import_file'];
                                    $path = is_array($file) ? array_key_first($file) : $file;

                                    try {
                                        $disk = FileUploadConfiguration::disk();
                                        $contents = Storage::disk($disk)->get($path);

                                        if ($contents === null) {
                                            throw new \RuntimeException('Could not read the uploaded file. Please try again.');
                                        }

                                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'csv';
                                        $localTmp = tempnam(sys_get_temp_dir(), 'stock_adjustment_import_').'.'.$ext;
                                        file_put_contents($localTmp, $contents);

                                        try {
                                            $imported = app(StockAdjustmentItemImportService::class)->importRows($localTmp, (int) $warehouseId);
                                        } finally {
                                            @unlink($localTmp);
                                        }

                                        $rows = self::mergeImportedRows($get('items') ?? [], $imported);

                                        $set('items', $rows);

                                        Notification::make()
                                            ->title(count($imported).' item(s) imported')
                                            ->body('Matching items had their adjustment quantities merged.')
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
                    ])->columnSpanFull()->columns(4),

                Repeater::make('items')
                    ->relationship()
                    ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => self::convertRepeaterDataToDisplayUnits($data))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::convertRepeaterDataToBaseUnits($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::convertRepeaterDataToBaseUnits($data))
                    ->table([
                        TableColumn::make('Inventory Item')->width('300px')->alignLeft(),
                        TableColumn::make('Adjustment')->alignLeft(),
                        TableColumn::make('Available')->alignLeft(),
                        TableColumn::make('Result')->alignLeft(),
                    ])
                    ->compact()
                    ->schema([
                        Select::make('inventory_item_id')
                            ->relationship('inventoryItem', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $warehouseId = $get('../../warehouse_id');

                                if ($state && $warehouseId) {
                                    $item = InventoryItem::find($state);
                                    $balance = InventoryBalance::where('inventory_item_id', $state)
                                        ->where('warehouse_id', $warehouseId)
                                        ->first();
                                    $system = StockTransferQuantity::displayQuantity($item, $balance?->quantity_on_hand ?? 0);
                                    $set('system_quantity', $system);

                                    $adj = (float) $get('adjustment_quantity');
                                    $set('new_quantity', $system + $adj);
                                    $set('difference', $adj);
                                }
                            })
                            ->disabled(fn ($get) => $get('../../status') === 'posted'),
                        TextInput::make('adjustment_quantity')
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->suffix(fn (Get $get): string => self::unitPrefixForItemId($get('inventory_item_id')))
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $system = (float) $get('system_quantity');
                                $adj = (float) $state;
                                $set('new_quantity', $system + $adj);
                                $set('difference', $adj);
                            })
                            ->helperText(fn (Get $get) => (float) $get('system_quantity') + (float) $get('adjustment_quantity') < 0
                                ? 'This adjustment will create negative stock. Please lower the negative quantity or correct the system quantity.'
                                : null)
                            ->disabled(fn ($get) => $get('../../status') === 'posted'),
                        TextInput::make('system_quantity')
                            ->numeric()
                            ->suffix(fn (Get $get): string => self::unitPrefixForItemId($get('inventory_item_id')))
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        TextInput::make('new_quantity')
                            ->numeric()
                            ->suffix(fn (Get $get): string => self::unitPrefixForItemId($get('inventory_item_id')))
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->default(0)
                            ->helperText(fn (Get $get) => (float) $get('new_quantity') < 0
                                ? 'Resulting stock would be negative. This adjustment cannot be posted.'
                                : null),
                        Hidden::make('difference')
                            ->default(0)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->disabled(fn ($record) => $record?->status === 'posted')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function convertRepeaterDataToDisplayUnits(array $data): array
    {
        $item = InventoryItem::find($data['inventory_item_id'] ?? null);

        foreach (['system_quantity', 'adjustment_quantity', 'new_quantity', 'difference'] as $quantityField) {
            $data[$quantityField] = StockTransferQuantity::displayQuantity($item, $data[$quantityField] ?? 0);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function convertRepeaterDataToBaseUnits(array $data): array
    {
        $item = InventoryItem::find($data['inventory_item_id'] ?? null);

        foreach (['system_quantity', 'adjustment_quantity', 'new_quantity', 'difference'] as $quantityField) {
            $data[$quantityField] = StockTransferQuantity::baseQuantity($item, $data[$quantityField] ?? 0);
        }

        return $data;
    }

    public static function unitPrefixForItemId(int|string|null $inventoryItemId): string
    {
        return StockTransferQuantity::unitLabel(InventoryItem::find($inventoryItemId));
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
            );

            if ($matchIndex !== false) {
                $merged = $rows[$matchIndex];
                $merged['adjustment_quantity'] = (float) ($merged['adjustment_quantity'] ?? 0) + (float) ($newRow['adjustment_quantity'] ?? 0);
                $merged['system_quantity'] = (float) ($merged['system_quantity'] ?? $newRow['system_quantity'] ?? 0);
                $merged['new_quantity'] = $merged['system_quantity'] + $merged['adjustment_quantity'];
                $merged['difference'] = $merged['adjustment_quantity'];
                $rows[$matchIndex] = $merged;

                continue;
            }

            $rows->push($newRow);
        }

        return $rows->values()->toArray();
    }
}

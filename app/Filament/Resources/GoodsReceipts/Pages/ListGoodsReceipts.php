<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Services\PostDirectGoodsReceipt;
use App\Support\WarehouseAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ListGoodsReceipts extends ListRecords
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('directReceipt')
                ->label('Direct receipt')
                ->icon('heroicon-o-inbox-arrow-down')
                ->authorize(fn (): bool => PostDirectGoodsReceipt::allows(auth()->user()))
                ->modalWidth('4xl')
                ->modalSubmitActionLabel('Post receipt')
                ->schema([Grid::make(2)->schema([
                    Select::make('source')->label('Receipt source')->options([
                        'supplier' => 'Supplier delivery without a purchase order',
                        'opening_stock' => 'Opening stock',
                    ])->default('supplier')->required()->live(),
                    Select::make('supplier_id')->label('Supplier')
                        ->options(fn (): array => Partner::query()->where('is_supplier', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->visible(fn (Get $get): bool => $get('source') === 'supplier')->required(),
                    TextInput::make('source_reference')
                        ->label(fn (Get $get): string => $get('source') === 'opening_stock' ? 'Stock count reference' : 'Delivery note or invoice reference')
                        ->required()->maxLength(120),
                    Select::make('warehouse_id')->label('Warehouse')
                        ->options(fn (): array => WarehouseAccess::warehouses()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?int => WarehouseAccess::defaultId())->searchable()->required(),
                    DatePicker::make('receipt_date')->default(today())->maxDate(today())->required(),
                    Repeater::make('items')->table([
                        TableColumn::make('Inventory item'),
                        TableColumn::make('Quantity'),
                    ])->schema([
                        Select::make('inventory_item_id')->label('Inventory item')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => InventoryItem::query()
                                ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                                ->orderBy('name')->limit(50)->get(['id', 'name', 'unit'])
                                ->mapWithKeys(fn (InventoryItem $item): array => [$item->id => self::itemLabel($item)])->all())
                            ->getOptionLabelUsing(fn ($value): ?string => self::itemLabel(InventoryItem::find($value)))
                            ->required()->distinct(),
                        TextInput::make('quantity_received')->label('Quantity')->numeric()->minValue(0.01)->step(0.01)->required(),
                    ])->helperText('Enter quantities in the inventory unit shown next to each item.')
                        ->compact()->defaultItems(1)->minItems(1)->reorderable(false)->addActionLabel('Add item')->columnSpanFull(),
                ])->columnSpanFull()])
                ->action(function (array $data, Schema $schema, PostDirectGoodsReceipt $post): void {
                    try {
                        $receipt = $post->handle($data);
                    } catch (ValidationException $exception) {
                        $errors = [];
                        foreach ($exception->errors() as $field => $messages) {
                            $errors[$schema->getStatePath().'.'.$field] = $messages;
                        }

                        throw ValidationException::withMessages($errors);
                    }

                    Notification::make()->success()->title('Receipt posted')->body($receipt->receipt_number)->send();
                }),
        ];
    }

    private static function itemLabel(?InventoryItem $item): ?string
    {
        return $item ? "{$item->name} ({$item->unit})" : null;
    }
}

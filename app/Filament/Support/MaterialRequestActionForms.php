<?php

namespace App\Filament\Support;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrderTask;
use App\Models\MaterialRequest;
use App\Models\Warehouse;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MaterialRequestActionForms
{
    public static function warehouseSelect(): Select
    {
        return Select::make('warehouse_id')
            ->label('Warehouse')
            ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
            ->default(fn () => Warehouse::where('is_default', true)->value('id'))
            ->required()
            ->searchable()
            ->live();
    }

    public static function singleIssueQuantityInput(): TextInput
    {
        return TextInput::make('quantity')
            ->label('Quantity to Issue')
            ->numeric()
            ->required()
            ->suffix(fn (MaterialRequest $record): ?string => $record->inventoryItem?->unit)
            ->default(fn (MaterialRequest $record): float => (float) $record->requested_quantity - (float) $record->issued_quantity)
            ->maxValue(fn (MaterialRequest $record): float => (float) $record->requested_quantity - (float) $record->issued_quantity)
            ->rules([
                fn (Get $get, MaterialRequest $record): Closure => self::issueQuantityRule(
                    fn (): ?MaterialRequest => $record,
                    fn (): mixed => $get('warehouse_id'),
                ),
            ])
            ->helperText('If this quantity exceeds the required amount for the task, it will wait for admin or operations approval before stock is moved.');
    }

    public static function issueItemsRepeater(?Closure $modifyQuery = null): Repeater
    {
        return Repeater::make('items')
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->schema([
                Hidden::make('material_request_id'),
                Select::make('inventory_item_id')
                    ->label('Material')
                    ->options(fn (): array => InventoryItem::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->label('Quantity to Issue')
                    ->hint(function (Get $get): string {
                        $materialRequest = MaterialRequest::query()->with('inventoryItem')->find($get('material_request_id'));
                        $warehouseId = $get('../../warehouse_id');

                        if (! $materialRequest) {
                            return 'Material request not found.';
                        }

                        $stock = self::availableStock((int) $warehouseId, (int) $materialRequest->inventory_item_id);

                        return 'Pending: '.self::remainingRequested($materialRequest)." | In Stock: {$stock}";
                    })
                    ->rules([
                        fn (Get $get): Closure => self::issueQuantityRule(
                            fn (): ?MaterialRequest => MaterialRequest::query()->with('inventoryItem')->find($get('material_request_id')),
                            fn (): mixed => $get('../../warehouse_id'),
                        ),
                    ])
                    ->helperText('If this exceeds the required quantity, it will be queued for approval instead of issuing immediately.'),
            ])
            ->columns(2)
            ->default(fn (Model $record): array => self::openMaterialRequests($record, $modifyQuery)
                ->map(fn (MaterialRequest $materialRequest): array => [
                    'material_request_id' => $materialRequest->id,
                    'inventory_item_id' => $materialRequest->inventory_item_id,
                    'quantity' => self::remainingRequested($materialRequest),
                ])
                ->toArray());
    }

    /**
     * @return array<int, mixed>
     */
    public static function requestMaterialsForm(JobOrderTask $record): array
    {
        return [
            Repeater::make('items')
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->schema([
                    Select::make('inventory_item_id')
                        ->label('Material')
                        ->options(fn (): array => InventoryItem::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->rules(['exists:inventory_items,id']),
                    TextInput::make('requested_quantity')
                        ->label('Quantity to Request')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->suffix(fn (Get $get): ?string => InventoryItem::query()->find($get('inventory_item_id'))?->unit)
                        ->hint(fn (Get $get): string => 'Required: '.((float) ($record->paper[(int) $get('paper_index')]['required_quantity'] ?? 0))),
                    Hidden::make('paper_index')
                        ->required(),
                ])
                ->columns(2)
                ->default(fn (): array => collect($record->paper ?? [])
                    ->filter(fn (mixed $item): bool => is_array($item) && filled($item['inventory_item_id'] ?? null))
                    ->map(fn (array $item, int $index): array => [
                        'inventory_item_id' => $item['inventory_item_id'],
                        'requested_quantity' => (float) ($item['required_quantity'] ?? 0),
                        'paper_index' => $index,
                    ])
                    ->values()
                    ->toArray()),
            TextInput::make('reason')
                ->label('Reason')
                ->required(),
        ];
    }

    public static function createMaterialRequests(JobOrderTask $record, array $data): int
    {
        $created = 0;

        foreach ($data['items'] ?? [] as $item) {
            $quantity = (float) ($item['requested_quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $paperIndex = (int) ($item['paper_index'] ?? -1);
            $paperRow = $record->paper[$paperIndex] ?? null;

            if (! is_array($paperRow) || blank($item['inventory_item_id'] ?? null)) {
                continue;
            }

            MaterialRequest::create([
                'job_order_task_id' => $record->id,
                'inventory_item_id' => $item['inventory_item_id'],
                'requested_quantity' => $quantity,
                'required_quantity' => (float) ($paperRow['required_quantity'] ?? 0),
                'reason' => $data['reason'],
            ]);

            $created++;
        }

        return $created;
    }

    protected static function issueQuantityRule(Closure $materialRequestResolver, Closure $warehouseIdResolver): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($materialRequestResolver, $warehouseIdResolver): void {
            if (blank($value)) {
                return;
            }

            $materialRequest = $materialRequestResolver();

            if (! $materialRequest) {
                $fail('The selected material request is no longer available.');

                return;
            }

            $quantity = (float) $value;
            $remainingRequested = self::remainingRequested($materialRequest);

            if ($quantity > $remainingRequested) {
                $fail("Only {$remainingRequested} {$materialRequest->inventoryItem?->unit} remains requested for {$materialRequest->inventoryItem?->name}.");

                return;
            }

            $warehouseId = $warehouseIdResolver();

            if (blank($warehouseId)) {
                return;
            }

            $stock = self::availableStock((int) $warehouseId, (int) $materialRequest->inventory_item_id);

            if ($quantity > $stock) {
                $fail("Only {$stock} {$materialRequest->inventoryItem?->unit} of {$materialRequest->inventoryItem?->name} is available in the selected warehouse.");
            }
        };
    }

    protected static function openMaterialRequests(Model $record, ?Closure $modifyQuery): Collection
    {
        $query = $record->materialRequests()
            ->whereColumn('issued_quantity', '<', 'requested_quantity')
            ->whereDoesntHave('pendingIssueApprovals', fn (Builder $query): Builder => $query->where('status', 'pending'));

        if ($modifyQuery) {
            $modifyQuery($query);
        }

        return $query->get();
    }

    protected static function remainingRequested(MaterialRequest $materialRequest): float
    {
        return round((float) $materialRequest->requested_quantity - (float) $materialRequest->issued_quantity, 2);
    }

    protected static function availableStock(int $warehouseId, int $inventoryItemId): float
    {
        if (! $warehouseId || ! $inventoryItemId) {
            return 0.0;
        }

        return (float) (InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $inventoryItemId)
            ->value('quantity_on_hand') ?? 0);
    }
}

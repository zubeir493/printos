<?php

namespace App\Filament\Resources\MaintenanceStoreEntries\Actions;

use App\Filament\Resources\MaintenanceStoreEntries\MaintenanceStoreEntryResource;
use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\MaintenanceStoreEntry;
use App\Services\MaintenanceStore;
use App\Support\WarehouseAccess;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class MaintenanceStoreActions
{
    public static function checkoutTool(): Action
    {
        return Action::make('checkoutTool')
            ->label('Check out tool')
            ->icon(Heroicon::OutlinedWrench)
            ->authorize(fn (): bool => MaintenanceStoreEntryResource::canViewAny())
            ->schema([
                ...self::stockFields('tools', 'Tool'),
                Select::make('employee_id')->label('Checked out to')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Employee::query()
                        ->where('status', 'active')
                        ->where(fn (Builder $query): Builder => self::searchEmployees($query, $search))
                        ->orderBy('first_name')->orderBy('last_name')->limit(50)->get(['id', 'employee_id', 'first_name', 'last_name'])
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => self::employeeLabel($employee)])->all())
                    ->getOptionLabelUsing(fn (int|string|null $value): ?string => self::employeeLabel(
                        Employee::query()->where('status', 'active')->find($value, ['id', 'employee_id', 'first_name', 'last_name']),
                    ))
                    ->required(),
                DatePicker::make('due_date')->label('Due back')->minDate(fn () => today()),
                Textarea::make('notes')->maxLength(1000),
            ])
            ->modalDescription('The tool is removed from available stock until it is returned.')
            ->modalSubmitActionLabel('Check out')
            ->successNotificationTitle('Tool checked out')
            ->action(fn (array $data, Schema $schema) => self::save($schema, fn (): MaintenanceStoreEntry => app(MaintenanceStore::class)->checkoutTool(
                (int) $data['inventory_item_id'],
                (int) $data['warehouse_id'],
                (int) $data['employee_id'],
                (float) $data['quantity'],
                $data['due_date'] ?? null,
                $data['notes'] ?? null,
            )));
    }

    public static function issueSparePart(): Action
    {
        return Action::make('issueSparePart')
            ->label('Issue spare parts')
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color('gray')
            ->authorize(fn (): bool => MaintenanceStoreEntryResource::canViewAny())
            ->schema([
                ...self::stockFields('spare_parts', 'Spare part'),
                Select::make('machine_id')->label('Machine')
                    ->options(fn (): array => Machine::query()->orderBy('name')->get(['id', 'name', 'code'])
                        ->mapWithKeys(fn (Machine $machine): array => [
                            $machine->id => filled($machine->code) ? "{$machine->name} ({$machine->code})" : $machine->name,
                        ])->all())
                    ->searchable()
                    ->required(),
                TextInput::make('reference')->label('Maintenance reference')->maxLength(120),
                Textarea::make('notes')->maxLength(1000),
            ])
            ->modalDescription('The issued quantity is deducted from the selected warehouse.')
            ->modalSubmitActionLabel('Issue parts')
            ->successNotificationTitle('Spare parts issued')
            ->action(fn (array $data, Schema $schema) => self::save($schema, fn (): MaintenanceStoreEntry => app(MaintenanceStore::class)->issueSparePart(
                (int) $data['inventory_item_id'],
                (int) $data['warehouse_id'],
                (int) $data['machine_id'],
                (float) $data['quantity'],
                $data['reference'] ?? null,
                $data['notes'] ?? null,
            )));
    }

    public static function returnTool(): Action
    {
        return Action::make('returnTool')
            ->label('Return tool')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->authorize(fn (MaintenanceStoreEntry $record): bool => MaintenanceStoreEntryResource::canViewAny()
                && WarehouseAccess::allows($record->warehouse_id))
            ->visible(fn (MaintenanceStoreEntry $record): bool => $record->type === MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT
                && $record->outstandingQuantity() > 0)
            ->modalDescription(fn (MaintenanceStoreEntry $record): string => "Return {$record->inventoryItem->name} to {$record->warehouse->name}.")
            ->schema([
                TextInput::make('quantity')->label('Quantity returned')->integer()->minValue(1)
                    ->maxValue(fn (MaintenanceStoreEntry $record): float => $record->outstandingQuantity())
                    ->default(fn (MaintenanceStoreEntry $record): float => $record->outstandingQuantity())
                    ->required(),
            ])
            ->modalSubmitActionLabel('Record return')
            ->successNotificationTitle('Tool return recorded')
            ->action(fn (array $data, MaintenanceStoreEntry $record, Schema $schema) => self::save(
                $schema,
                fn (): MaintenanceStoreEntry => app(MaintenanceStore::class)->returnTool($record, (float) $data['quantity']),
            ));
    }

    public static function reportToolDisposition(): Action
    {
        return Action::make('reportToolDisposition')->label('Report lost or damaged')
            ->icon('heroicon-o-exclamation-triangle')
            ->authorize(fn (MaintenanceStoreEntry $record): bool => MaintenanceStoreEntryResource::canViewAny()
                && WarehouseAccess::allows($record->warehouse_id))
            ->visible(fn (MaintenanceStoreEntry $record): bool => $record->type === MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT
                && $record->outstandingQuantity() > 0)
            ->schema([
                Select::make('disposition')->options(['lost' => 'Lost', 'damaged' => 'Damaged'])->required(),
                TextInput::make('quantity')->integer()->minValue(1)
                    ->maxValue(fn (MaintenanceStoreEntry $record): float => $record->outstandingQuantity())->required(),
                TextInput::make('reason')->required()->maxLength(1000),
            ])
            ->successNotificationTitle('Tool closeout recorded')
            ->action(fn (array $data, MaintenanceStoreEntry $record, Schema $schema) => self::save(
                $schema,
                fn () => app(MaintenanceStore::class)->disposeTool($record, (float) $data['quantity'], $data['disposition'], $data['reason']),
            ));
    }

    /** @return list<Select|TextInput> */
    private static function stockFields(string $type, string $label): array
    {
        return [
            Select::make('warehouse_id')->label('Warehouse')
                ->options(fn () => WarehouseAccess::warehouses()->orderBy('name')->pluck('name', 'id'))
                ->default(fn (): ?int => WarehouseAccess::defaultId())
                ->searchable()->required()->live(),
            Select::make('inventory_item_id')->label($label)
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => InventoryItem::query()
                    ->where('type', $type)
                    ->where(fn (Builder $query): Builder => $query->whereLike('name', "%{$search}%")->orWhereLike('sku', "%{$search}%"))
                    ->orderBy('name')->limit(50)->get(['id', 'name', 'sku'])
                    ->mapWithKeys(fn (InventoryItem $item): array => [$item->id => self::itemLabel($item)])->all())
                ->getOptionLabelUsing(fn (int|string|null $value): ?string => self::itemLabel(
                    InventoryItem::query()->where('type', $type)->find($value),
                ))
                ->required()->live(),
            TextInput::make('quantity')->numeric()->required()
                ->when($type === 'tools', fn (TextInput $field): TextInput => $field->integer()->minValue(1),
                    fn (TextInput $field): TextInput => $field->step(0.01)->minValue(0.01)->rule('decimal:0,2'))
                ->helperText(fn (Get $get): ?string => self::stockHelp((int) $get('warehouse_id'), (int) $get('inventory_item_id'), $type)),
        ];
    }

    private static function stockHelp(int $warehouseId, int $itemId, string $type): ?string
    {
        if (! $warehouseId || ! $itemId || ! WarehouseAccess::allows($warehouseId)) {
            return null;
        }

        $item = InventoryItem::query()->where('type', $type)->find($itemId);
        if (! $item) {
            return null;
        }

        $available = InventoryBalance::query()->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $itemId)->value('quantity_on_hand');
        $amount = Number::format((float) $available, maxPrecision: 2);

        return trim("Available in this warehouse · {$amount} {$item->unit}");
    }

    private static function itemLabel(?InventoryItem $item): ?string
    {
        return $item ? "{$item->name} ({$item->sku})" : null;
    }

    private static function employeeLabel(?Employee $employee): ?string
    {
        return $employee ? "{$employee->full_name} ({$employee->employee_id})" : null;
    }

    private static function searchEmployees(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/', trim($search), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $query->where(fn (Builder $names): Builder => $names
                ->whereLike('first_name', "%{$word}%")
                ->orWhereLike('last_name', "%{$word}%")
                ->orWhereLike('employee_id', "%{$word}%"));
        }

        return $query;
    }

    private static function save(Schema $schema, Closure $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$schema->getStatePath().'.'.$field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}

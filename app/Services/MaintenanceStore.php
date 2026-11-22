<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\MaintenanceStoreEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\WarehouseAccess;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MaintenanceStore
{
    public function __construct(private InventoryService $inventory) {}

    public function checkoutTool(int $inventoryItemId, int $warehouseId, int $employeeId, float $quantity, ?string $dueDate = null, ?string $notes = null): MaintenanceStoreEntry
    {
        $actor = $this->authorize($warehouseId);
        Validator::validate(['quantity' => $quantity, 'due_date' => $dueDate, 'notes' => $notes], [
            'quantity' => ['required', 'integer', 'min:1', 'max:9999999999999'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! Employee::query()->where('status', 'active')->whereKey($employeeId)->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Select an active employee.']);
        }

        return $this->recordOutflow($actor, $inventoryItemId, $warehouseId, $quantity, 'tools', [
            'type' => MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT,
            'employee_id' => $employeeId,
            'due_date' => $dueDate,
            'notes' => $notes,
        ]);
    }

    public function issueSparePart(int $inventoryItemId, int $warehouseId, int $machineId, float $quantity, ?string $reference = null, ?string $notes = null): MaintenanceStoreEntry
    {
        $actor = $this->authorize($warehouseId);
        Validator::validate(['quantity' => $quantity, 'reference' => $reference, 'notes' => $notes], [
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999.99'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! Machine::query()->whereKey($machineId)->exists()) {
            throw ValidationException::withMessages(['machine_id' => 'Select a machine.']);
        }

        return $this->recordOutflow($actor, $inventoryItemId, $warehouseId, $quantity, 'spare_parts', [
            'type' => MaintenanceStoreEntry::TYPE_SPARE_PART_ISSUE,
            'machine_id' => $machineId,
            'reference' => $reference,
            'notes' => $notes,
        ]);
    }

    public function returnTool(MaintenanceStoreEntry $entry, float $quantity): MaintenanceStoreEntry
    {
        $this->authorize($entry->warehouse_id);
        Validator::validate(['quantity' => $quantity], ['quantity' => ['required', 'integer', 'min:1', 'max:9999999999999']]);

        return DB::transaction(function () use ($entry, $quantity): MaintenanceStoreEntry {
            $checkout = MaintenanceStoreEntry::query()->with(['inventoryItem', 'warehouse'])
                ->lockForUpdate()->findOrFail($entry->getKey());
            $this->authorize($checkout->warehouse_id);

            if ($checkout->type !== MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT || $quantity > $checkout->outstandingQuantity()) {
                throw ValidationException::withMessages(['quantity' => 'Return a quantity no greater than the tools still checked out.']);
            }

            $this->inventory->createMovement(
                $checkout->inventoryItem, $checkout->warehouse, MaintenanceStoreEntry::MOVEMENT_TOOL_RETURN,
                $quantity, referenceType: $checkout->getMorphClass(), referenceId: $checkout->id,
            );
            $checkout->update(['returned_quantity' => (float) $checkout->returned_quantity + $quantity]);

            return $checkout;
        }, attempts: 3);
    }

    /** @param array{type: string, employee_id?: int, machine_id?: int, due_date?: ?string, reference?: ?string, notes?: ?string} $details */
    private function recordOutflow(User $actor, int $inventoryItemId, int $warehouseId, float $quantity, string $itemType, array $details): MaintenanceStoreEntry
    {
        return DB::transaction(function () use ($actor, $inventoryItemId, $warehouseId, $quantity, $itemType, $details): MaintenanceStoreEntry {
            $item = InventoryItem::query()->where('type', $itemType)->lockForUpdate()->find($inventoryItemId);
            if (! $item) {
                throw ValidationException::withMessages(['inventory_item_id' => 'Select an item of the correct type.']);
            }

            $warehouse = Warehouse::find($warehouseId);
            if (! $warehouse) {
                throw ValidationException::withMessages(['warehouse_id' => 'Select a warehouse.']);
            }

            $balance = InventoryBalance::query()->where('inventory_item_id', $item->id)
                ->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
            if ($quantity > (float) ($balance?->quantity_on_hand ?? 0)) {
                throw ValidationException::withMessages(['quantity' => 'There is insufficient stock in the selected warehouse.']);
            }

            $entry = MaintenanceStoreEntry::create([
                ...$details,
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'recorded_by' => $actor->id,
                'quantity' => $quantity,
                'returned_quantity' => 0,
            ]);
            $this->inventory->createMovement(
                $item, $warehouse, $entry->type, $quantity,
                referenceType: $entry->getMorphClass(), referenceId: $entry->id,
            );

            return $entry;
        }, attempts: 3);
    }

    private function authorize(int $warehouseId): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && in_array($actor->role, [UserRole::Admin, UserRole::Warehouse], true), 403);
        WarehouseAccess::authorize($warehouseId, $actor);

        return $actor;
    }
}

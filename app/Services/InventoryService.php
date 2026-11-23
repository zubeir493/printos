<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\MaintenanceStoreEntry;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function receiveStock(
        int $itemId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $referenceType,
        int $referenceId
    ) {
        $item = InventoryItem::findOrFail($itemId);
        $warehouse = Warehouse::findOrFail($warehouseId);

        $this->createMovement(
            $item,
            $warehouse,
            $referenceType === 'material_return' ? 'material_return' : 'purchase',
            $quantity,
            $unitCost,
            $referenceType,
            $referenceId
        );
    }

    public function returnJobMaterials(int $itemId, int $warehouseId, float $quantity, float $unitCost, int $jobOrderId): StockMovement
    {
        return $this->createMovement(InventoryItem::findOrFail($itemId), Warehouse::findOrFail($warehouseId),
            'material_return', $quantity, $unitCost, JobOrder::class, $jobOrderId);
    }

    public function receiveStockInPurchaseUnit(
        int $itemId,
        int $warehouseId,
        float $purchaseQuantity,
        float $purchaseUnitPrice,
        string $referenceType,
        int $referenceId
    ) {
        $item = InventoryItem::findOrFail($itemId);
        $warehouse = Warehouse::findOrFail($warehouseId);

        $factor = (float) ($item->conversion_factor ?: 1);
        $baseQuantity = $purchaseQuantity * $factor;
        $baseUnitPrice = $purchaseUnitPrice / $factor;

        return $this->createMovement(
            $item,
            $warehouse,
            'purchase',
            $baseQuantity,
            $baseUnitPrice,
            $referenceType,
            $referenceId
        );
    }

    public function createMovement(
        InventoryItem $item,
        Warehouse $warehouse,
        string $type,
        float $quantity,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ) {
        $signedQty = $this->resolveSignedQuantity($type, $quantity);

        return StockMovement::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'type' => $type,
            'quantity' => $signedQty,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost ? abs($quantity) * $unitCost : null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'movement_date' => now(),
        ]);
    }

    public function consumeStock(
        int $itemId,
        int $warehouseId,
        float $quantity,
        string $referenceType,
        int $referenceId
    ) {
        $item = InventoryItem::findOrFail($itemId);
        $warehouse = Warehouse::findOrFail($warehouseId);

        // Pre-validation (Optional but good for UX)
        $balance = InventoryBalance::where([
            'inventory_item_id' => $itemId,
            'warehouse_id' => $warehouseId,
        ])->lockForUpdate()->first();

        if (! $balance || $balance->quantity_on_hand < $quantity) {
            throw new \Exception("Insufficient stock for {$item->name} in the selected warehouse.");
        }

        $costedJob = $referenceType === JobOrder::class && JobOrder::query()->whereKey($referenceId)->where('uses_production_ticket', true)->exists();
        if ($costedJob && (float) $item->average_cost <= 0) {
            throw ValidationException::withMessages(['quantity' => "Enter a verified acquisition cost for {$item->name} before issuing it to this job."]);
        }
        $unitCost = (float) $item->average_cost;
        if ($unitCost <= 0) {
            $unitCost = (float) ($item->price ?? 0) / ($item->hasPurchaseUnit() ? (float) $item->conversion_factor : 1);
        }

        return $this->createMovement(
            $item,
            $warehouse,
            'consumption',
            $quantity,
            $unitCost,
            $referenceType,
            $referenceId
        );
    }

    private function resolveSignedQuantity(string $type, float $quantity): float
    {
        return match ($type) {
            'purchase', 'transfer_in', 'material_return', 'production_output', MaintenanceStoreEntry::MOVEMENT_TOOL_RETURN => abs($quantity),
            'consumption', 'transfer_out', 'sale', MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT, MaintenanceStoreEntry::TYPE_SPARE_PART_ISSUE => -abs($quantity),
            'adjustment' => $quantity, // Preserve sign for manual adjustments
            default => $quantity,
        };
    }
}

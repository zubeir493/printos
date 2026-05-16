<?php

namespace App\Support;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use Illuminate\Support\Number;

class StockTransferQuantity
{
    public static function displayQuantity(?InventoryItem $item, float|int|string|null $baseQuantity): float
    {
        $quantity = (float) ($baseQuantity ?? 0);

        if (! $item) {
            return $quantity;
        }

        return $item->hasPurchaseUnit()
            ? round($item->toPurchaseUnits($quantity), 4)
            : $quantity;
    }

    public static function baseQuantity(?InventoryItem $item, float|int|string|null $displayQuantity): float
    {
        $quantity = (float) ($displayQuantity ?? 0);

        if (! $item) {
            return $quantity;
        }

        return $item->hasPurchaseUnit()
            ? round($item->toBaseUnits($quantity), 4)
            : $quantity;
    }

    public static function unitLabel(?InventoryItem $item): string
    {
        if (! $item) {
            return 'unit';
        }

        return $item->hasPurchaseUnit()
            ? ($item->purchase_unit ?: 'unit')
            : ($item->unit ?: 'unit');
    }

    public static function formattedQuantity(?InventoryItem $item, float|int|string|null $baseQuantity): string
    {
        $quantity = self::displayQuantity($item, $baseQuantity);

        return Number::format($quantity, maxPrecision: 4).' '.self::unitLabel($item);
    }

    public static function availableDisplayQuantity(?int $inventoryItemId, ?int $warehouseId): ?float
    {
        if (! $inventoryItemId || ! $warehouseId) {
            return null;
        }

        $item = InventoryItem::find($inventoryItemId);

        if (! $item) {
            return null;
        }

        $balance = InventoryBalance::where([
            'inventory_item_id' => $inventoryItemId,
            'warehouse_id' => $warehouseId,
        ])->first();

        return self::displayQuantity($item, $balance?->quantity_on_hand ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function convertRepeaterDataToDisplayUnits(array $data): array
    {
        $item = InventoryItem::find($data['inventory_item_id'] ?? null);

        $data['quantity'] = self::displayQuantity($item, $data['quantity'] ?? 0);
        $data['unit_label'] = self::unitLabel($item);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function convertRepeaterDataToBaseUnits(array $data): array
    {
        $item = InventoryItem::find($data['inventory_item_id'] ?? null);

        $data['quantity'] = self::baseQuantity($item, $data['quantity'] ?? 0);
        unset($data['unit_label']);

        return $data;
    }
}

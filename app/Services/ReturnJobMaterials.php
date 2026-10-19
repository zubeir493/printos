<?php

namespace App\Services;

use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReturnJobMaterials
{
    /** @return array<int, array<string, int|float>> */
    public function rowsFor(JobOrder $job): array
    {
        $balances = [];
        foreach ($job->issuedBalanceByWarehouse() as $warehouseId => $items) {
            foreach ($items as $item) {
                $balances[$item->inventory_item_id][$warehouseId] = max(0, -(float) $item->net_quantity);
            }
        }
        $rows = [];
        foreach ($job->materialRequests()->where('issued_quantity', '>', 0)->orderBy('id')->get() as $request) {
            $remaining = (float) $request->issued_quantity;
            foreach ($balances[$request->inventory_item_id] ?? [] as $warehouseId => $available) {
                $quantity = min($remaining, $available);
                if ($quantity <= 0) {
                    continue;
                }
                $rows[] = ['material_request_id' => $request->id, 'inventory_item_id' => $request->inventory_item_id,
                    'warehouse_id' => (int) $warehouseId, 'returnable_quantity' => $quantity, 'quantity' => 0];
                $balances[$request->inventory_item_id][$warehouseId] -= $quantity;
                $remaining -= $quantity;
            }
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $items */
    public function handle(JobOrder $job, array $items): void
    {
        DB::transaction(function () use ($job, $items): void {
            $job = JobOrder::query()->lockForUpdate()->findOrFail($job->id);
            if (auth()->check()) {
                Gate::authorize('returnMaterials', $job);
            }
            foreach ($items as $item) {
                $quantity = (float) ($item['quantity'] ?? 0);
                if ($quantity === 0.0) {
                    continue;
                }
                $request = $job->materialRequests()->lockForUpdate()->findOrFail($item['material_request_id']);
                $warehouseId = (int) ($item['warehouse_id'] ?? 0);
                $outstanding = -(float) $job->materialMovements()
                    ->whereIn('type', ['consumption', 'material_return'])
                    ->where('inventory_item_id', $request->inventory_item_id)->where('warehouse_id', $warehouseId)->sum('quantity');
                if (! is_finite($quantity) || $quantity <= 0 || $quantity > (float) $request->issued_quantity || $quantity > $outstanding) {
                    throw ValidationException::withMessages(['items' => 'Return only materials still issued from the selected warehouse.']);
                }
                $consumption = $job->materialMovements()->where('type', 'consumption')
                    ->where('inventory_item_id', $request->inventory_item_id)->where('warehouse_id', $warehouseId)
                    ->selectRaw('SUM(total_cost) as cost, SUM(ABS(quantity)) as quantity')->first();
                $unitCost = (float) $consumption->quantity > 0
                    ? (float) $consumption->cost / (float) $consumption->quantity : 0;
                app(InventoryService::class)->returnJobMaterials($request->inventory_item_id, $warehouseId, $quantity, $unitCost, $job->id);
                $request->decrement('issued_quantity', $quantity);
            }
        });
    }
}

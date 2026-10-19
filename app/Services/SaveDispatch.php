<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\InventoryBalance;
use App\Models\JobOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDispatch
{
    public function handle(Dispatch $dispatch, float $quantity): void
    {
        DB::transaction(function () use ($dispatch, $quantity): void {
            $lockedDispatch = Dispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $job = JobOrder::query()->lockForUpdate()->findOrFail($lockedDispatch->job_order_id);
            if ($quantity < 0 || $quantity != floor($quantity)) {
                throw ValidationException::withMessages(['data.quantity' => 'Dispatch quantity must be a nonnegative whole number.']);
            }
            $item = app(DispatchStockService::class)->inventoryItemForJobOrder($job);
            $originalMovements = $lockedDispatch->stockMovements()->get();
            foreach ($originalMovements as $movement) {
                InventoryBalance::query()->where('inventory_item_id', $movement->inventory_item_id)
                    ->where('warehouse_id', $movement->warehouse_id)->lockForUpdate()->first();
                $movement->delete();
            }
            $lockedDispatch->dispatchItems()->delete();
            if ($quantity === 0.0) {
                return;
            }
            if ($item) {
                InventoryBalance::query()->where('inventory_item_id', $item->id)
                    ->where('warehouse_id', $lockedDispatch->warehouse_id)->lockForUpdate()->first();
            }
            $issue = app(DispatchStockService::class)->dispatchIssue($job->id, $lockedDispatch->warehouse_id, $quantity);
            if ($issue) {
                throw ValidationException::withMessages(['data.quantity' => $issue]);
            }
            $lockedDispatch->dispatchItems()->create(['job_order_id' => $job->id, 'quantity' => $quantity]);
            StockMovement::create([
                'inventory_item_id' => $item->id, 'warehouse_id' => $lockedDispatch->warehouse_id,
                'type' => 'dispatch', 'reference_type' => Dispatch::class,
                'reference_id' => $lockedDispatch->id, 'quantity' => -$quantity, 'movement_date' => now(),
            ]);
        });
        $dispatch->unsetRelation('dispatchItems');
    }
}

<?php

namespace App\Services\JobOrders;

use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogJobOrderProduction
{
    public function handle(JobOrder $jobOrder, int $warehouseId, float $quantity, ?int $existingItemId = null): StockMovement
    {
        return DB::transaction(function () use ($jobOrder, $warehouseId, $quantity, $existingItemId): StockMovement {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);
            Warehouse::findOrFail($warehouseId);
            if ((string) $locked->status !== 'production' || $quantity <= 0 || floor($quantity) !== $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Log a positive quantity on a job in production.']);
            }
            if (filled($locked->deliverables) && ! $locked->isReadyForProduction()) {
                throw ValidationException::withMessages(['quantity' => 'Approve required deliverables before logging production.']);
            }
            $internal = $locked->production_mode === 'make_to_stock';
            if ($locked->output_inventory_item_id && $existingItemId && $existingItemId !== $locked->output_inventory_item_id) {
                throw ValidationException::withMessages(['existing_inventory_item_id' => 'Production output must use the same inventory item throughout the job.']);
            }
            $item = $locked->outputInventoryItem;
            if (! $item && $internal && $existingItemId) {
                $item = InventoryItem::query()->where('type', 'finished_good')->findOrFail($existingItemId);
            }
            if (! $item) {
                $item = InventoryItem::firstOrCreate(['sku' => ($internal ? 'FG-JOB-' : 'JOB-').$locked->id], [
                    'name' => $locked->name.' ('.$locked->job_order_number.')',
                    'type' => $internal ? 'finished_good' : 'wip', 'unit' => 'pcs',
                    'is_sellable' => $internal, 'price' => 0,
                ]);
            }
            $locked->updateQuietly(['output_inventory_item_id' => $item->id]);
            $movement = StockMovement::create([
                'inventory_item_id' => $item->id, 'warehouse_id' => $warehouseId,
                'type' => 'production_output', 'reference_type' => JobOrder::class,
                'reference_id' => $locked->id, 'quantity' => $quantity, 'movement_date' => now(),
            ]);
            app(JobOrderWorkflow::class)->sync($locked);

            return $movement;
        });
    }
}

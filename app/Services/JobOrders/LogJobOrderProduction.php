<?php

namespace App\Services\JobOrders;

use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\WarehouseAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LogJobOrderProduction
{
    public function handle(JobOrder $jobOrder, ?int $warehouseId, float $quantity, ?int $existingItemId = null): ?StockMovement
    {
        $movement = DB::transaction(function () use ($jobOrder, $warehouseId, $quantity, $existingItemId): ?StockMovement {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);
            if (auth()->check()) {
                Gate::authorize('logProduction', $locked);
            }
            if ((string) $locked->status !== 'production' || ! is_finite($quantity) || $quantity <= 0 || floor($quantity) !== $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Log a positive quantity on a job in production.']);
            }
            if (filled($locked->deliverables) && ! $locked->isReadyForProduction()) {
                throw ValidationException::withMessages(['quantity' => 'Approve required deliverables before logging production.']);
            }
            if ($locked->uses_production_ticket) {
                if ($locked->production_completed_at || $quantity > 4294967295 - $locked->produced_quantity) {
                    throw ValidationException::withMessages(['quantity' => 'This run has already been recorded or the quantity exceeds the supported limit.']);
                }
                $missing = collect($locked->materials_summary)->filter(fn (array $material): bool => $material['issued'] < $material['planned'])->pluck('material_name');
                if ($missing->isNotEmpty()) {
                    throw ValidationException::withMessages(['quantity' => 'Issue the planned quantities before logging output: '.$missing->implode(', ').'.']);
                }
                if ($locked->requiresCostedStockReceipt()) {
                    $locked->increment('recorded_output_quantity', (int) $quantity);
                    app(JobOrderWorkflow::class)->sync($locked);

                    return null;
                }
            }
            if (! $warehouseId) {
                throw ValidationException::withMessages(['warehouse_id' => 'Select an output warehouse.']);
            }
            WarehouseAccess::authorize($warehouseId);
            Warehouse::findOrFail($warehouseId);
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
        }, attempts: 3);
        $jobOrder->refresh();

        return $movement;
    }
}

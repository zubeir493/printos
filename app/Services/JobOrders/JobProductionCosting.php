<?php

namespace App\Services\JobOrders;

use App\Models\Account;
use App\Models\CostEstimate;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\WarehouseAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class JobProductionCosting
{
    public const CATEGORIES = [
        'materials' => 'Materials',
        'labor' => 'Direct labor',
        'press' => 'Press and machine cost',
        'finishing' => 'Binding and finishing',
        'overhead' => 'Production overhead',
    ];

    /** @return array<string, float> */
    public function estimateCosts(CostEstimate $estimate): array
    {
        $costs = array_fill_keys(array_keys(self::CATEGORIES), 0.0);
        foreach ($estimate->lines as $line) {
            $category = match (strtolower($line->category)) {
                'material', 'tooling' => 'materials',
                'machine', 'press' => 'press',
                'labor', 'labour' => 'labor',
                'overhead' => 'overhead',
                default => 'finishing',
            };
            $costs[$category] += (float) $line->total;
        }
        $costs['overhead'] += (float) $estimate->overhead_amount;

        return $costs;
    }

    public function issuedMaterialCost(JobOrder $job): float
    {
        $movements = $job->materialMovements()->whereIn('type', ['consumption', 'material_return']);

        return round((float) (clone $movements)->where('type', 'consumption')->sum('total_cost')
            - (float) $movements->where('type', 'material_return')->sum('total_cost'), 2);
    }

    /** @param array<string, mixed> $costs */
    public function saveWorksheet(JobOrder $job, array $costs, bool $actual): void
    {
        DB::transaction(function () use ($job, $costs, $actual): void {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($job->id);
            Gate::authorize('editCostWorksheet', $locked);
            $status = (string) $locked->status;
            if ($actual ? ! in_array($status, ['production', 'completed'], true) : (! in_array($status, ['draft', 'design'], true) || $locked->proforma_task_id)) {
                throw ValidationException::withMessages(['costs' => 'This section of the cost worksheet is locked at the current stage.']);
            }
            $values = $this->validateCosts($costs);
            if ($actual) {
                $values['materials'] = $this->issuedMaterialCost($locked);
                $locked->update(['actual_production_costs' => $values]);
            } else {
                $locked->forceFill([
                    'estimated_production_costs' => $values, 'cost_estimate_id' => null,
                    'cost_reviewed_at' => null, 'cost_reviewed_by' => null,
                ])->save();
            }
        }, attempts: 3);
        $job->refresh();
    }

    public function eligibleEstimates(string $jobType, int $quantity, int|string|null $partnerId): Builder
    {
        return CostEstimate::query()->where('status', 'finalized')->where('job_type', $jobType)
            ->where('quantity', $quantity)
            ->where(fn (Builder $query): Builder => $query->whereNull('partner_id')->orWhere('partner_id', $partnerId))
            ->latest('id');
    }

    public function useEstimate(JobOrder $job, ?int $estimateId): void
    {
        DB::transaction(function () use ($job, $estimateId): void {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($job->id);
            Gate::authorize('editCostWorksheet', $locked);
            if (! in_array((string) $locked->status, ['draft', 'design'], true) || $locked->proforma_task_id) {
                throw ValidationException::withMessages(['cost_estimate_id' => 'The cost estimate is locked at the current stage.']);
            }
            if ($estimateId === $locked->cost_estimate_id) {
                return;
            }
            $estimate = $estimateId === null ? null : $this->eligibleEstimates($locked->job_type, $locked->quantity, $locked->partner_id)
                ->with('lines')->find($estimateId);
            if ($estimateId !== null && ! $estimate) {
                throw ValidationException::withMessages(['cost_estimate_id' => 'Select a finalized estimate for this job’s product, quantity and customer.']);
            }
            $locked->forceFill([
                'cost_estimate_id' => $estimate?->id, 'estimated_production_costs' => $estimate ? $this->estimateCosts($estimate) : null,
                'cost_reviewed_at' => null, 'cost_reviewed_by' => null,
            ])->save();
        }, attempts: 3);
        $job->refresh();
    }

    public function approveEstimate(JobOrder $job): void
    {
        DB::transaction(function () use ($job): void {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($job->id);
            Gate::authorize('reviewProductionCost', $locked);
            if (! in_array((string) $locked->status, ['draft', 'design'], true) || array_sum($locked->estimated_production_costs ?? []) <= 0) {
                throw ValidationException::withMessages(['costs' => 'Record a positive estimated production cost before approval.']);
            }
            $locked->forceFill(['cost_reviewed_at' => now(), 'cost_reviewed_by' => auth()->id()])->save();
        }, attempts: 3);
        $job->refresh();
    }

    public function receiveFinishedStock(JobOrder $job, int $warehouseId, ?int $inventoryItemId, string $taxCode): void
    {
        DB::transaction(function () use ($job, $warehouseId, $inventoryItemId, $taxCode): void {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($job->id);
            Gate::authorize('reviewProductionCost', $locked);
            if ($locked->stock_received_at) {
                return;
            }
            if (! $locked->requiresCostedStockReceipt() || (string) $locked->status !== 'production'
                || ! $locked->productionIsComplete() || $locked->actual_production_costs === null) {
                throw ValidationException::withMessages(['costs' => 'Finish recording the print run and enter actual costs before receiving finished stock.']);
            }
            WarehouseAccess::authorize($warehouseId);
            $warehouse = Warehouse::findOrFail($warehouseId);
            $materialCost = $this->issuedMaterialCost($locked);
            if ($locked->materialMovements()->where('type', 'consumption')->whereNull('unit_cost')->exists() || $materialCost < 0) {
                throw ValidationException::withMessages(['costs' => 'Resolve missing or inconsistent material issue costs before closing this run.']);
            }
            $costs = [...$locked->actual_production_costs, 'materials' => $materialCost];
            $total = round(array_sum($costs), 2);
            if ($total <= 0) {
                throw ValidationException::withMessages(['costs' => 'The finished run must have a positive reviewed cost.']);
            }
            if (! array_key_exists($taxCode, InventoryItem::salesTaxOptions())) {
                throw ValidationException::withMessages(['sales_tax_code' => 'Select a valid sales tax code.']);
            }
            $item = $inventoryItemId
                ? InventoryItem::query()->where('type', 'finished_good')->where('is_sellable', true)->lockForUpdate()->findOrFail($inventoryItemId)
                : InventoryItem::firstOrCreate(['sku' => 'FG-JOB-'.$locked->id], [
                    'name' => $locked->name.' ('.$locked->job_order_number.')',
                    'type' => 'finished_good', 'unit' => 'pcs', 'is_sellable' => true, 'category' => 'other',
                ]);
            if ($item->hasPurchaseUnit() || ! in_array(strtolower($item->unit), ['pc', 'pcs', 'piece', 'pieces', 'copy', 'copies', 'each', 'unit', 'units'], true)) {
                throw ValidationException::withMessages(['inventory_item_id' => 'Select a finished product stocked by individual copy or piece.']);
            }
            $item->forceFill(['sales_tax_code' => $taxCode, 'production_valued' => true])->save();
            $locked->forceFill([
                'output_inventory_item_id' => $item->id, 'production_warehouse_id' => $warehouse->id,
                'actual_production_costs' => $costs, 'cost_reviewed_by' => auth()->id(),
                'cost_reviewed_at' => now(), 'stock_received_at' => now(),
            ])->save();
            StockMovement::create([
                'inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id,
                'type' => 'production_output', 'reference_type' => $locked->getMorphClass(),
                'reference_id' => $locked->id, 'quantity' => $locked->recorded_output_quantity,
                'unit_cost' => round($total / $locked->recorded_output_quantity, 6),
                'total_cost' => $total, 'movement_date' => now(),
            ]);
            $this->postCapitalization($locked, $total, $materialCost);
        }, attempts: 3);
        $job->refresh();
    }

    /** @param array<string, mixed> $costs @return array<string, float> */
    private function validateCosts(array $costs): array
    {
        $rules = array_fill_keys(array_keys(self::CATEGORIES), ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999']);
        $values = Validator::validate($costs, $rules);

        return array_map(fn ($value): float => (float) $value, array_replace(array_fill_keys(array_keys($rules), 0), $values));
    }

    public function postMaterialMovement(JobOrder $job, StockMovement $movement): void
    {
        $amount = (float) $movement->total_cost;
        if ($amount <= 0) {
            return;
        }
        $inventory = Account::getSystemAccount('1500', 'Inventory', 'Asset');
        $wip = Account::getSystemAccount('1510', 'Production Work in Progress', 'Asset');
        $returned = $movement->type === 'material_return';
        $journal = JournalEntry::create([
            'date' => $movement->movement_date, 'reference' => 'Job materials #'.$movement->id,
            'source_type' => $movement->getMorphClass(), 'source_id' => $movement->id,
            'narration' => ($returned ? 'Return materials for ' : 'Issue materials to ').$job->job_order_number,
            'total_debit' => $amount, 'total_credit' => $amount, 'status' => 'posted', 'posted_at' => now(),
        ]);
        $journal->journalItems()->createMany([
            ['account_id' => $returned ? $inventory->id : $wip->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $returned ? $wip->id : $inventory->id, 'debit' => 0, 'credit' => $amount],
        ]);
    }

    private function postCapitalization(JobOrder $job, float $total, float $materials): void
    {
        $inventory = Account::getSystemAccount('1500', 'Inventory', 'Asset');
        $wip = Account::getSystemAccount('1510', 'Production Work in Progress', 'Asset');
        $appliedConversion = Account::getSystemAccount('5090', 'Production Costs Applied', 'Expense');
        $conversion = round($total - $materials, 2);
        $journal = JournalEntry::create([
            'date' => today(), 'reference' => 'Production #'.$job->job_order_number,
            'source_type' => $job->getMorphClass(), 'source_id' => $job->id,
            'narration' => 'Capitalize and receive finished stock for '.$job->job_order_number,
            'total_debit' => round($total + $conversion, 2), 'total_credit' => round($total + $conversion, 2),
            'status' => 'posted', 'posted_at' => now(),
        ]);
        $journal->journalItems()->createMany(array_values(array_filter([
            ['account_id' => $wip->id, 'debit' => $conversion, 'credit' => 0],
            ['account_id' => $appliedConversion->id, 'debit' => 0, 'credit' => $conversion],
            ['account_id' => $inventory->id, 'debit' => $total, 'credit' => 0],
            ['account_id' => $wip->id, 'debit' => 0, 'credit' => $total],
        ], fn (array $row): bool => $row['debit'] > 0 || $row['credit'] > 0)));
    }
}

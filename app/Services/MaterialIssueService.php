<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\JobOrder;
use App\Models\MaterialIssueApproval;
use App\Models\MaterialRequest;
use App\Models\User;
use App\Notifications\MaterialIssueDecisionNotification;
use App\UserRole;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class MaterialIssueService
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    public function issue(MaterialRequest $materialRequest, int $warehouseId, float $quantity, ?User $actor = null): array
    {
        return DB::transaction(function () use ($materialRequest, $warehouseId, $quantity, $actor) {
            $materialRequest = MaterialRequest::query()
                ->with(['inventoryItem', 'jobOrderTask.jobOrder'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            $quantity = round($quantity, 2);

            if ($quantity <= 0) {
                return ['status' => 'skipped'];
            }

            if ($materialRequest->pendingIssueApprovals()->exists()) {
                throw new \Exception("{$materialRequest->inventoryItem->name} already has a pending over-issue approval.");
            }

            $remainingRequested = round($materialRequest->requested_quantity - $materialRequest->issued_quantity, 2);

            if ($quantity > $remainingRequested) {
                throw new \Exception("Cannot issue more than the remaining requested quantity for {$materialRequest->inventoryItem->name}.");
            }

            $stock = (float) (InventoryBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->where('inventory_item_id', $materialRequest->inventory_item_id)
                ->value('quantity_on_hand') ?? 0);

            if ($stock < $quantity) {
                throw new \Exception("Insufficient stock for {$materialRequest->inventoryItem->name} in the selected warehouse.");
            }

            if (($materialRequest->issued_quantity + $quantity) > $materialRequest->required_quantity) {
                $approval = $materialRequest->issueApprovals()->create([
                    'warehouse_id' => $warehouseId,
                    'requested_by' => $actor?->id,
                    'quantity' => $quantity,
                    'status' => 'pending',
                    'reason' => 'Requested issue exceeds the required material quantity for this task.',
                ]);

                $this->notifyApprovers($approval);

                return [
                    'status' => 'pending_approval',
                    'approval' => $approval,
                ];
            }

            $this->inventoryService->consumeStock(
                $materialRequest->inventory_item_id,
                $warehouseId,
                $quantity,
                JobOrder::class,
                $materialRequest->jobOrderTask->job_order_id
            );

            $materialRequest->increment('issued_quantity', $quantity);

            return ['status' => 'issued'];
        });
    }

    public function approve(MaterialIssueApproval $approval, ?User $actor = null, ?string $notes = null): void
    {
        DB::transaction(function () use ($approval, $actor, $notes) {
            $approval = MaterialIssueApproval::query()
                ->with(['materialRequest.inventoryItem', 'materialRequest.jobOrderTask.jobOrder', 'requester'])
                ->lockForUpdate()
                ->findOrFail($approval->id);

            if ($approval->status !== 'pending') {
                throw new \Exception('This over-issue request has already been processed.');
            }

            $materialRequest = $approval->materialRequest;

            $stock = (float) (InventoryBalance::query()
                ->where('warehouse_id', $approval->warehouse_id)
                ->where('inventory_item_id', $materialRequest->inventory_item_id)
                ->value('quantity_on_hand') ?? 0);

            if ($stock < $approval->quantity) {
                throw new \Exception("Insufficient stock for {$materialRequest->inventoryItem->name} in the selected warehouse.");
            }

            $remainingRequested = round($materialRequest->requested_quantity - $materialRequest->issued_quantity, 2);

            if ($approval->quantity > $remainingRequested) {
                throw new \Exception("The pending approval quantity for {$materialRequest->inventoryItem->name} is now greater than the remaining requested quantity.");
            }

            $this->inventoryService->consumeStock(
                $materialRequest->inventory_item_id,
                $approval->warehouse_id,
                (float) $approval->quantity,
                JobOrder::class,
                $materialRequest->jobOrderTask->job_order_id
            );

            $materialRequest->increment('issued_quantity', $approval->quantity);

            $approval->update([
                'status' => 'approved',
                'processed_by' => $actor?->id,
                'decision_notes' => $notes,
                'approved_at' => now(),
                'rejected_at' => null,
            ]);

            // Notify the requester of the decision
            if ($approval->requested_by) {
                $approval->requester?->notify(new MaterialIssueDecisionNotification($approval));
            }
        });
    }

    public function reject(MaterialIssueApproval $approval, ?User $actor = null, ?string $notes = null): void
    {
        DB::transaction(function () use ($approval, $actor, $notes) {
            $approval = MaterialIssueApproval::query()
                ->with(['materialRequest.inventoryItem', 'materialRequest.jobOrderTask.jobOrder', 'requester'])
                ->lockForUpdate()
                ->findOrFail($approval->id);

            if ($approval->status !== 'pending') {
                throw new \Exception('This over-issue request has already been processed.');
            }

            $approval->update([
                'status' => 'rejected',
                'processed_by' => $actor?->id,
                'decision_notes' => $notes,
                'approved_at' => null,
                'rejected_at' => now(),
            ]);

            // Notify the requester of the decision
            if ($approval->requested_by) {
                $approval->requester?->notify(new MaterialIssueDecisionNotification($approval));
            }
        });
    }

    protected function notifyApprovers(MaterialIssueApproval $approval): void
    {
        $approval->loadMissing(['materialRequest.inventoryItem', 'materialRequest.jobOrderTask.jobOrder', 'warehouse']);

        $users = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Operations->value])
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Material over-issue approval needed')
            ->body(sprintf(
                '%s requested %.2f of %s from %s for %s.',
                $approval->requester?->name ?? 'A user',
                (float) $approval->quantity,
                $approval->materialRequest->inventoryItem->name,
                $approval->warehouse->name,
                $approval->materialRequest->jobOrderTask->jobOrder->job_order_number
            ))
            ->warning()
            ->sendToDatabase($users, isEventDispatched: true);
    }
}

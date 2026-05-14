<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use App\Models\Dispatch;
use App\Models\InventoryItem;
use App\Models\JobOrderTask;
use App\Models\StockMovement;
use App\Services\DispatchStockService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditDispatch extends EditRecord
{
    protected static string $resource = DispatchResource::class;

    protected array $quantities = [];

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['quantities'] = $this->record->dispatchItems->pluck('quantity', 'job_order_task_id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->quantities = $data['quantities'] ?? [];

        $this->haltIfDispatchHasStockIssue($data['warehouse_id'] ?? $this->record->warehouse_id, $this->quantities);

        unset($data['quantities']);

        return $data;
    }

    protected function afterSave(): void
    {
        if (empty($this->quantities)) {
            // Delete all stock movements and dispatch items
            $this->record->dispatchItems()->delete();
            $this->record->stockMovements()->delete();

            return;
        }

        // Get existing dispatch items
        $existingItems = $this->record->dispatchItems->keyBy('job_order_task_id');

        foreach ($this->quantities as $taskId => $quantity) {
            $qty = (int) $quantity;

            if ($qty <= 0) {
                if (isset($existingItems[$taskId])) {
                    // Create reversal stock movement for deleted dispatch item
                    $this->createStockMovement($taskId, $existingItems[$taskId]->quantity, true);
                    $existingItems[$taskId]->delete();
                }

                continue;
            }

            if (isset($existingItems[$taskId])) {
                $oldQty = $existingItems[$taskId]->quantity;
                $existingItems[$taskId]->update(['quantity' => $qty]);

                // Create stock movement for the difference
                $difference = $qty - $oldQty;
                if ($difference != 0) {
                    $this->createStockMovement($taskId, $difference);
                }

                unset($existingItems[$taskId]); // Mark as processed
            } else {
                $this->record->dispatchItems()->create([
                    'job_order_task_id' => $taskId,
                    'quantity' => $qty,
                ]);

                // Create stock movement for new dispatch item
                $this->createStockMovement($taskId, $qty);
            }
        }

        // Delete any remaining items that weren't in the new quantities
        foreach ($existingItems as $taskId => $item) {
            $this->createStockMovement($taskId, $item->quantity, true);
            $item->delete();
        }
    }

    private function createStockMovement(int $taskId, int $quantity, bool $isReversal = false): void
    {
        $task = JobOrderTask::with('jobOrder')->find($taskId);
        if (! $task) {
            return;
        }

        $productionMode = $task->jobOrder->production_mode;
        $inventoryItem = null;

        // Determine the correct inventory item based on production mode
        if ($productionMode === 'make_to_order') {
            // Client Job - look for WIP item with new SKU format
            $itemSku = 'TASK-'.$taskId;
            $inventoryItem = InventoryItem::where('sku', $itemSku)->first();
        } else {
            // Internal Job - look for finished good (could be existing or task-specific)
            $inventoryItem = InventoryItem::where('type', 'finished_good')
                ->where('name', 'like', "%{$task->name}%")
                ->first();
        }

        if ($inventoryItem && $this->record->warehouse_id) {
            $movementQuantity = $isReversal ? abs($quantity) : -$quantity;

            try {
                StockMovement::create([
                    'inventory_item_id' => $inventoryItem->id,
                    'warehouse_id' => $this->record->warehouse_id,
                    'type' => 'dispatch',
                    'reference_type' => Dispatch::class,
                    'reference_id' => $this->record->id,
                    'quantity' => $movementQuantity,
                    'movement_date' => now(),
                ]);
            } catch (\Throwable $exception) {
                $this->notifyDispatchStockIssue($exception->getMessage());

                throw (new Halt)->rollBackDatabaseTransaction();
            }
        }
    }

    /**
     * @param  array<int|string, int|float|string|null>  $quantities
     */
    private function haltIfDispatchHasStockIssue(int|string|null $warehouseId, array $quantities): void
    {
        $dispatchStock = app(DispatchStockService::class);
        $currentQuantities = $this->record->dispatchItems()->pluck('quantity', 'job_order_task_id');

        foreach ($quantities as $taskId => $quantity) {
            $issue = $dispatchStock->dispatchIssue(
                $taskId,
                $warehouseId,
                $quantity,
                $currentQuantities->get($taskId, 0),
            );

            if (! $issue) {
                continue;
            }

            $this->notifyDispatchStockIssue($issue);

            throw new Halt;
        }
    }

    private function notifyDispatchStockIssue(string $message): void
    {
        Notification::make()
            ->title('Dispatch cannot be saved')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }
}

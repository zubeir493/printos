<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Models\Dispatch;
use App\Models\InventoryItem;
use App\Models\JobOrderTask;
use App\Models\StockMovement;
use App\Services\DispatchStockService;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class CreateDispatch extends CreateRecord
{
    protected static string $resource = DispatchResource::class;

    protected static bool $canCreateAnother = false;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * Temporarily hold quantities from the form so we can create DispatchItem records.
     *
     * @var array<int|string,int>
     */
    protected array $quantities = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->quantities = $data['quantities'] ?? [];
        $data['status'] = 'pending';

        $this->haltIfDispatchHasStockIssue($data['warehouse_id'] ?? null, $this->quantities);

        // Remove quantities so they are not mass-assigned to Dispatch model
        unset($data['quantities']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (empty($this->quantities) || ! $this->record) {
            return;
        }

        foreach ($this->quantities as $taskId => $quantity) {
            $qty = (float) $quantity;

            if ($qty <= 0) {
                continue;
            }

            $this->record->dispatchItems()->create([
                'job_order_task_id' => $taskId,
                'quantity' => $qty,
            ]);

            // Record stock movement (consume WIP or Finished Goods)
            $task = JobOrderTask::with('jobOrder')->find($taskId);
            if (! $task) {
                continue;
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
                try {
                    StockMovement::create([
                        'inventory_item_id' => $inventoryItem->id,
                        'warehouse_id' => $this->record->warehouse_id,
                        'type' => 'dispatch',
                        'reference_type' => Dispatch::class,
                        'reference_id' => $this->record->id,
                        'quantity' => -$qty, // Negative for consumption
                        'movement_date' => now(),
                    ]);
                } catch (\Throwable $exception) {
                    $this->notifyDispatchStockIssue($exception->getMessage());

                    throw (new Halt)->rollBackDatabaseTransaction();
                }
            }
        }
    }

    /**
     * @param  array<int|string, int|float|string|null>  $quantities
     */
    private function haltIfDispatchHasStockIssue(int|string|null $warehouseId, array $quantities): void
    {
        $dispatchStock = app(DispatchStockService::class);

        foreach ($quantities as $taskId => $quantity) {
            $issue = $dispatchStock->dispatchIssue($taskId, $warehouseId, $quantity);

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

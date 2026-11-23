<?php

namespace App\Filament\Resources\JobOrders\Actions;

use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Services\JobOrders\JobProductionCosting;
use App\Support\Money;
use App\Support\WarehouseAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class JobOrderCostActions
{
    public static function worksheet(bool $actual = false): Action
    {
        $permission = fn (JobOrder $record): bool => Gate::allows('editCostWorksheet', $record)
            && ($actual ? in_array((string) $record->status, ['production', 'completed'], true)
                : ! $record->proforma_task_id && in_array((string) $record->status, ['draft', 'design'], true));

        return Action::make($actual ? 'record_actual_costs' : 'record_estimated_costs')
            ->label($actual ? 'Record actual production costs' : 'Record estimated production costs')
            ->icon('heroicon-o-calculator')->authorize($permission)->visible($permission)
            ->fillForm(fn (JobOrder $record): array => ($actual ? $record->actual_production_costs : $record->estimated_production_costs) ?? [])
            ->schema(function (JobOrder $record) use ($actual): array {
                $fields = [];
                foreach (JobProductionCosting::CATEGORIES as $name => $label) {
                    $fields[] = $actual && $name === 'materials'
                        ? Placeholder::make('issued_materials')->label('Net material issues')->content(fn (): string => Money::format(app(JobProductionCosting::class)->issuedMaterialCost($record)))
                        : TextInput::make($name)->label($label)->numeric()->minValue(0)->maxValue(999999999)->step(0.01)->suffix(fn (): string => Money::suffix());
                }

                return $fields;
            })
            ->action(fn (JobOrder $record, array $data) => self::perform(
                fn () => app(JobProductionCosting::class)->saveWorksheet($record, $data, $actual),
                'Production costs saved',
            ));
    }

    public static function linkEstimate(): Action
    {
        $permission = fn (JobOrder $record): bool => Gate::allows('editCostWorksheet', $record) && ! $record->proforma_task_id
            && in_array((string) $record->status, ['draft', 'design'], true);

        return Action::make('link_cost_estimate')->label('Use a finalized cost estimate')->icon('heroicon-o-link')
            ->authorize($permission)->visible($permission)
            ->schema([
                Select::make('cost_estimate_id')->label('Finalized estimate')->searchable()->required()
                    ->options(fn (JobOrder $record) => app(JobProductionCosting::class)->eligibleEstimates($record->job_type, $record->quantity, $record->partner_id)
                        ->pluck('estimate_number', 'id')),
            ])
            ->action(fn (JobOrder $record, array $data) => self::perform(
                fn () => app(JobProductionCosting::class)->useEstimate($record, (int) $data['cost_estimate_id']),
                'Cost estimate applied',
            ));
    }

    public static function approveEstimate(): Action
    {
        $permission = fn (JobOrder $record): bool => Gate::allows('reviewProductionCost', $record)
            && ! $record->cost_reviewed_at && ! $record->proforma_task_id
            && in_array((string) $record->status, ['draft', 'design'], true) && array_sum($record->estimated_production_costs ?? []) > 0;

        return Action::make('approve_production_estimate')->label('Approve estimated costs')->icon('heroicon-o-check-badge')
            ->authorize($permission)->visible($permission)->requiresConfirmation()
            ->action(fn (JobOrder $record) => self::perform(
                fn () => app(JobProductionCosting::class)->approveEstimate($record),
                'Estimated costs approved',
            ));
    }

    public static function receive(): Action
    {
        $permission = fn (JobOrder $record): bool => Gate::allows('reviewProductionCost', $record)
            && $record->requiresCostedStockReceipt() && (string) $record->status === 'production'
            && filled($record->production_completed_at) && ! $record->stock_received_at;

        return Action::make('receive_finished_stock')->label('Review costs and receive stock')->icon('heroicon-o-archive-box-arrow-down')
            ->authorize($permission)->visible($permission)
            ->modalDescription('Confirm the actual cost of the run and the sales tax code for future sales of this product.')
            ->fillForm(fn (JobOrder $record): array => [
                'warehouse_id' => $record->production_warehouse_id ?? WarehouseAccess::defaultId(),
                'inventory_item_id' => $record->output_inventory_item_id,
                'sales_tax_code' => $record->outputInventoryItem?->sales_tax_code,
            ])
            ->schema([
                Placeholder::make('receipt_cost')->label('Actual run cost')->content(function (JobOrder $record): string {
                    if ($record->actual_production_costs === null) {
                        return 'Record actual production costs before receiving stock.';
                    }

                    return Money::format(array_sum([
                        ...$record->actual_production_costs, 'materials' => app(JobProductionCosting::class)->issuedMaterialCost($record),
                    ]));
                }),
                Select::make('warehouse_id')->label('Finished-goods warehouse')->options(fn () => WarehouseAccess::warehouses()->orderBy('name')->pluck('name', 'id'))->required(),
                Select::make('inventory_item_id')->label('Existing finished product')->searchable()->live()
                    ->options(fn () => InventoryItem::query()->where('type', 'finished_good')->where('is_sellable', true)->orderBy('name')->pluck('name', 'id'))
                    ->afterStateUpdated(fn ($state, $set) => $set('sales_tax_code', $state ? InventoryItem::find($state)?->sales_tax_code : null))
                    ->helperText('Leave blank to create a finished product for this run.'),
                Select::make('sales_tax_code')->label('Sales tax code')->options(InventoryItem::salesTaxOptions())->required(),
            ])
            ->action(fn (JobOrder $record, array $data) => self::perform(
                fn () => app(JobProductionCosting::class)->receiveFinishedStock(
                    $record, (int) $data['warehouse_id'], filled($data['inventory_item_id'] ?? null) ? (int) $data['inventory_item_id'] : null, $data['sales_tax_code'],
                ),
                'Finished stock received',
            ));
    }

    public static function productTax(): Action
    {
        $permission = fn (JobOrder $record): bool => Gate::allows('reviewProductionCost', $record)
            && filled($record->stock_received_at) && filled($record->output_inventory_item_id);

        return Action::make('set_product_sales_tax')->label('Update product sales tax code')->icon('heroicon-o-receipt-percent')
            ->authorize($permission)->visible($permission)
            ->fillForm(fn (JobOrder $record): array => ['sales_tax_code' => $record->outputInventoryItem?->sales_tax_code])
            ->schema([Select::make('sales_tax_code')->options(InventoryItem::salesTaxOptions())->required()])
            ->action(function (JobOrder $record, array $data): void {
                Gate::authorize('reviewProductionCost', $record);
                $record->outputInventoryItem()->firstOrFail()->update(['sales_tax_code' => $data['sales_tax_code']]);
                Notification::make()->title('Sales tax code updated')->success()->send();
            });
    }

    private static function perform(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            Notification::make()->title('Review the job order')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->persistent()->send();

            return;
        }
        Notification::make()->title($success)->success()->send();
    }
}

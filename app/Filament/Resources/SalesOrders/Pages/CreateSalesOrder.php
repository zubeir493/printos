<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\PanelAccess;
use App\Models\SalesOrder;
use App\Services\SalesOrderPaymentService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    /**
     * @var array{amount: float, method: string, bank_id: int|string|null, reference: string|null}
     */
    protected array $initialPaymentData = [
        'amount' => 0.0,
        'method' => 'cash',
        'bank_id' => null,
        'reference' => null,
    ];

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageSalesOrders();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $hasInitialPayment = (bool) ($data['has_initial_payment'] ?? false);
        $initialPaymentMethod = $data['initial_payment_method'] ?? 'cash';

        $this->initialPaymentData = [
            'amount' => $hasInitialPayment ? (float) ($data['initial_payment_amount'] ?? 0) : 0.0,
            'method' => $initialPaymentMethod,
            'bank_id' => $hasInitialPayment && in_array($initialPaymentMethod, ['bank', 'bank_transfer', 'cheque', 'check'], true)
                ? ($data['initial_payment_bank_id'] ?? null)
                : null,
            'reference' => $hasInitialPayment ? ($data['initial_payment_reference'] ?? null) : null,
        ];

        unset(
            $data['has_initial_payment'],
            $data['initial_payment_amount'],
            $data['initial_payment_method'],
            $data['initial_payment_bank_id'],
            $data['initial_payment_reference'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if ($record->payment_mode === 'cash') {
            try {
                DB::transaction(fn (): mixed => $record->update(['status' => SalesOrder::STATUS_COMPLETED]));

                Notification::make()
                    ->title('Sales Order Completed')
                    ->body($record->order_number.' was completed and payment was recorded.')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Sales Order Completion Failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->persistent()
                    ->send();
            }

            return;
        }

        if ($this->initialPaymentData['amount'] <= 0) {
            return;
        }

        try {
            $payments = app(SalesOrderPaymentService::class)->processMultiplePayments($record, [
                $this->initialPaymentData,
            ]);

            Notification::make()
                ->title('Initial Payment Recorded')
                ->body(count($payments).' payment recorded for '.$record->order_number.'. Submit the items when the order is ready for fulfillment.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Initial Payment Failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}

<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\PanelAccess;
use App\Services\SalesOrderPaymentService;
use Filament\Notifications\Notification;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageSalesOrders();
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $data = $this->data;

        // Process payments if this is a cash sale with payment data
        if ($record->payment_mode === 'cash' && ! empty($data['payments'])) {
            try {
                $paymentService = app(SalesOrderPaymentService::class);
                $payments = $paymentService->processMultiplePayments($record, $data['payments']);

                if (count($payments) > 0) {
                    Notification::make()
                        ->title('Payments processed successfully')
                        ->body(count($payments).' payment(s) created for '.$record->order_number)
                        ->success()
                        ->send();
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Payment processing failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->persistent()
                    ->send();
            }
        }
    }
}

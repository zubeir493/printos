<?php

namespace App\Filament\Imports;

use App\Models\Payment;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class PaymentImporter extends Importer
{
    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('payment_number')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('transaction_type')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('partner')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('payment_date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('withholding_amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('direction')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('method')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('reference')
                ->rules(['max:255']),
            ImportColumn::make('voided_at')
                ->rules(['datetime']),
            ImportColumn::make('voided_by')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('void_reason')
                ->rules(['max:255']),
            ImportColumn::make('payment_type')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('account')
                ->relationship(),
            ImportColumn::make('bank')
                ->relationship(),
            ImportColumn::make('expenseAccount')
                ->relationship(),
            ImportColumn::make('pettyCashAccount')
                ->relationship(),
            ImportColumn::make('expense_tracking_type')
                ->rules(['max:255']),
            ImportColumn::make('expenseTrackingItem')
                ->relationship(),
            ImportColumn::make('expenseTrackingEmployee')
                ->relationship(),
            ImportColumn::make('expenseTrackingBid')
                ->relationship(),
            ImportColumn::make('payable_type')
                ->rules(['max:255']),
            ImportColumn::make('payable_id')
                ->numeric()
                ->rules(['integer']),
        ];
    }

    public function resolveRecord(): Payment
    {
        return new Payment;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your payment import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

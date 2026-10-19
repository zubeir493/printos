<?php

namespace App\Filament\Imports;

use App\Models\JobOrder;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class JobOrderImporter extends Importer
{
    protected static ?string $model = JobOrder::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('job_order_number')->rules(['nullable', 'max:255', 'unique:job_orders,job_order_number']),
            ImportColumn::make('name')->requiredMapping()->rules(['required', 'max:255']),
            ImportColumn::make('quantity')->requiredMapping()->numeric()->rules(['required', 'integer', 'min:1']),
            ImportColumn::make('size')->rules(['nullable', 'max:255']),
            ImportColumn::make('instructions')->rules(['nullable', 'string']),
            ImportColumn::make('partner')->relationship(),
            ImportColumn::make('job_type')->requiredMapping()->rules(['required', 'in:books,packages,labels,vouchers']),
            ImportColumn::make('production_mode')->requiredMapping()->rules(['required', 'in:make_to_order,make_to_stock']),
            ImportColumn::make('cost_calc_file')->requiredMapping()->rules(['required', 'max:255']),
            ImportColumn::make('submission_date')->requiredMapping()->rules(['required', 'date']),
            ImportColumn::make('due_date')->rules(['nullable', 'date']),
            ImportColumn::make('subtotal')->requiredMapping()->numeric()->rules(['required', 'numeric', 'min:0']),
            ImportColumn::make('remarks'),
        ];
    }

    public function resolveRecord(): JobOrder
    {
        return new JobOrder(['status' => 'draft', 'services' => [], 'paper' => [], 'deliverables' => []]);
    }

    protected function beforeSave(): void
    {
        if ($this->record->production_mode === 'make_to_order' && ! $this->record->partner_id) {
            throw ValidationException::withMessages(['partner' => 'Client jobs require a customer.']);
        }
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Imported '.Number::format($import->successful_rows).' job orders; '.Number::format($import->getFailedRowsCount()).' rows failed.';
    }
}

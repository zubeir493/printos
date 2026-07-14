<?php

namespace App\Filament\Imports;

use App\Models\Employee;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class EmployeeImporter extends Importer
{
    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('employee_id')
                ->label('Employee ID')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('attendance_device_id')
                ->label('Punch Machine AC No')
                ->rules(['max:255']),
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('phone')
                ->rules(['max:255']),
            ImportColumn::make('hire_date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('termination_date')
                ->rules(['date']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('employment_type')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('department')
                ->rules(['max:255']),
            ImportColumn::make('position')
                ->rules(['max:255']),
            ImportColumn::make('tax_id')
                ->label('Tax ID')
                ->rules(['max:255']),
            ImportColumn::make('pension_enabled')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('basic_salary')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric']),
            ImportColumn::make('transport_allowance')
                ->numeric()
                ->rules(['numeric']),
            ImportColumn::make('payment_method')
                ->rules(['max:255']),
            ImportColumn::make('bank_name')
                ->rules(['max:255']),
            ImportColumn::make('account_number')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): Employee
    {
        return Employee::firstOrNew([
            'employee_id' => $this->data['employee_id'],
        ]);
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your employee import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

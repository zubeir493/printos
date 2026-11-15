<?php

namespace App\Filament\Imports;

use App\Models\Employee;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class EmployeeImporter extends Importer
{
    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('employee_id')
                ->label('Employee ID')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('attendance_device_id')
                ->label('Punch Machine AC No')->guess(['AC No', 'AC No.', 'FP No', 'FP No.'])
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('first_name')
                ->label('First Name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('last_name')
                ->label('Last Name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('phone')
                ->label('Phone')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('hire_date')
                ->label('Hire Date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('termination_date')
                ->label('Termination Date')
                ->rules(['nullable', 'date', 'after_or_equal:hire_date']),
            ImportColumn::make('status')
                ->label('Status')
                ->castStateUsing(fn (?string $state): ?string => $state === null ? null : strtolower($state))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('employment_type')
                ->label('Employment Type')
                ->castStateUsing(fn (?string $state): ?string => $state === null ? null : str_replace(['-', ' '], '_', strtolower($state)))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('department')
                ->label('Department')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('position')
                ->label('Position')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('tax_id')
                ->label('Tax ID')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('pension_enabled')
                ->label('Pension Enabled')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('basic_salary')
                ->label('Monthly Rate')->guess(['Basic Salary', 'Salary'])
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),
            ImportColumn::make('transport_allowance')
                ->label('Transportation Allowance')->guess(['Transport Allowance'])
                ->numeric()
                ->castStateUsing(fn (mixed $state): mixed => $state ?? 0)
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('payment_method')
                ->label('Payment Method')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('bank_name')
                ->label('Bank Name')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('account_number')
                ->label('Account Number')
                ->rules(['nullable', 'string', 'max:255']),
        ];
    }

    public function resolveRecord(): Employee
    {
        return Employee::firstOrNew([
            'employee_id' => $this->data['employee_id'] ?? '',
        ]);
    }

    public function getValidationRules(): array
    {
        $rules = parent::getValidationRules();
        foreach (['employee_id', 'attendance_device_id'] as $field) {
            if (array_key_exists($field, $rules)) {
                $rules[$field][] = Rule::unique('employees', $field)->ignore($this->record?->getKey());
            }
        }
        if (isset($rules['status'])) {
            $rules['status'][] = Rule::in(['active', 'inactive', 'terminated']);
        }
        if (isset($rules['employment_type'])) {
            $rules['employment_type'][] = Rule::in(['permanent', 'contract', 'temporary', 'part_time']);
        }

        return $rules;
    }

    protected function beforeSave(): void
    {
        Gate::forUser($this->import->user)->authorize($this->record->exists ? 'update' : 'create', $this->record->exists ? $this->record : Employee::class);

        if ($this->record->exists && $this->record->isDirty(['basic_salary', 'transport_allowance', 'pension_enabled'])) {
            throw new RowImportFailedException('Use Revise salary to change an existing employee’s salary, allowance or pension.');
        }
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

<?php

namespace App\Filament\Exports;

use App\Models\Employee;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class EmployeeExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('employee_id')
                ->label('Employee ID'),
            ExportColumn::make('attendance_device_id')
                ->label('Punch Machine AC No'),
            ExportColumn::make('first_name')->label('First Name'),
            ExportColumn::make('last_name')->label('Last Name'),
            ExportColumn::make('phone')
                ->label('Phone'),
            ExportColumn::make('hire_date')
                ->label('Hire Date')
                ->state(fn (Employee $record): ?string => $record->hire_date?->toDateString()),
            ExportColumn::make('termination_date')
                ->label('Termination Date')
                ->state(fn (Employee $record): ?string => $record->termination_date?->toDateString()),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('employment_type')
                ->label('Employment Type'),
            ExportColumn::make('department')
                ->label('Department'),
            ExportColumn::make('position')
                ->label('Position'),
            ExportColumn::make('tax_id')
                ->label('Tax ID'),
            ExportColumn::make('pension_enabled')
                ->label('Pension Enabled')
                ->formatStateUsing(fn (bool $state): int => (int) $state),
            ExportColumn::make('basic_salary')
                ->label('Monthly Rate'),
            ExportColumn::make('transport_allowance')
                ->label('Transportation Allowance'),
            ExportColumn::make('payment_method')
                ->label('Payment Method'),
            ExportColumn::make('bank_name')
                ->label('Bank Name'),
            ExportColumn::make('account_number')
                ->label('Account Number'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your employee export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

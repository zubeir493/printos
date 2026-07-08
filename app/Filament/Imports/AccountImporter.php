<?php

namespace App\Filament\Imports;

use App\Enums\ExpenseTrackingType;
use App\Models\Account;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountImporter extends Importer
{
    private const ACCOUNT_TYPES = [
        'Asset',
        'Liability',
        'Equity',
        'Revenue',
        'Expense',
    ];

    protected static ?string $model = Account::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->requiredMapping()
                ->example('1010')
                ->helperText('Existing account codes are updated. New account codes are created.')
                ->rules(['required', 'max:255']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->example('Cash')
                ->rules(['required', 'max:255']),
            ImportColumn::make('type')
                ->requiredMapping()
                ->example('Asset')
                ->castStateUsing(fn (?string $state): ?string => blank($state) ? null : Str::headline($state))
                ->rules(['required', Rule::in(self::ACCOUNT_TYPES)]),
            ImportColumn::make('default_tracking_type')
                ->label('Default Expense Tracking')
                ->example(ExpenseTrackingType::NONE->value)
                ->castStateUsing(fn (?string $state): ?string => self::normalizeTrackingType($state))
                ->rules(['nullable', 'max:255', Rule::in(array_keys(ExpenseTrackingType::options()))]),
        ];
    }

    public function resolveRecord(): Account
    {
        if (blank($this->data['code'] ?? null)) {
            return new Account;
        }

        return Account::firstOrNew([
            'code' => $this->data['code'],
        ]);
    }

    protected function afterFill(): void
    {
        if ($this->record->type !== 'Expense') {
            $this->record->default_tracking_type = null;

            return;
        }

        $this->record->default_tracking_type ??= ExpenseTrackingType::NONE->value;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    private static function normalizeTrackingType(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $normalizedState = Str::of($state)->trim()->lower()->snake()->toString();

        return match ($normalizedState) {
            'no_tracking' => ExpenseTrackingType::NONE->value,
            default => $normalizedState,
        };
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your account import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

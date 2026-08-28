<?php

namespace App\Filament\Resources\CashDeposits\Pages;

use App\Filament\Resources\CashDeposits\Actions\CashDepositActions;
use App\Filament\Resources\CashDeposits\CashDepositResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class ViewCashDeposit extends ViewRecord
{
    protected static string $resource = CashDepositResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Cash deposit #'.$this->getRecord()->deposit_number;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $sourceAccount = $record->cashAccount ?? $record->incomeAccount;

        $data['status_display'] = Str::of((string) $record->status)
            ->replace('_', ' ')
            ->title()
            ->toString();
        $data['source_account'] = $sourceAccount
            ? $sourceAccount->code.' - '.$sourceAccount->name
            : '-';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            CashDepositActions::make(includeEdit: true, includeReconcile: true),
        ];
    }
}

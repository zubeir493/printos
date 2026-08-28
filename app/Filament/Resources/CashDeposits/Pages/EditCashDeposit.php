<?php

namespace App\Filament\Resources\CashDeposits\Pages;

use App\Filament\Resources\CashDeposits\Actions\CashDepositActions;
use App\Filament\Resources\CashDeposits\CashDepositResource;
use App\Models\CashDeposit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditCashDeposit extends EditRecord
{
    protected static string $resource = CashDepositResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Edit #'.$this->getRecord()->deposit_number;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $depositType = $data['deposit_type'] ?? $this->record->deposit_type;

        if ($depositType === CashDeposit::TYPE_OTHER_SOURCES) {
            $data['cash_account_id'] = null;
            $data['income_account_id'] = CashDeposit::otherIncomeAccount()->id;
        } elseif ($depositType !== CashDeposit::TYPE_OTHER_INCOME) {
            $data['cash_account_id'] = CashDeposit::cashOnHandAccount()->id;
            $data['income_account_id'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            CashDepositActions::make(includeView: true, includeDelete: true),
        ];
    }
}

<?php

namespace App\Filament\Resources\CashDeposits\Pages;

use App\Filament\Resources\CashDeposits\CashDepositResource;
use App\Models\CashDeposit;
use Filament\Resources\Pages\CreateRecord;

class CreateCashDeposit extends CreateRecord
{
    protected static string $resource = CashDepositResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $depositType = $data['deposit_type'] ?? CashDeposit::TYPE_CASH_TRANSFER;

        if ($depositType === CashDeposit::TYPE_OTHER_SOURCES) {
            $data['cash_account_id'] = null;
            $data['income_account_id'] = CashDeposit::otherIncomeAccount()->id;
        } else {
            $data['cash_account_id'] = CashDeposit::cashOnHandAccount()->id;
        }

        return $data;
    }
}

<?php

namespace App\Filament\Resources\CashDeposits\Pages;

use App\Filament\Resources\CashDeposits\Actions\CashDepositActions;
use App\Filament\Resources\CashDeposits\CashDepositResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewCashDeposit extends ViewRecord
{
    protected static string $resource = CashDepositResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Cash deposit #'.$this->getRecord()->deposit_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            CashDepositActions::make(includeEdit: true),
        ];
    }
}

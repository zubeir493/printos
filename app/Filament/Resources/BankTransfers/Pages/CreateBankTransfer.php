<?php

namespace App\Filament\Resources\BankTransfers\Pages;

use App\Filament\Resources\BankTransfers\BankTransferResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateBankTransfer extends CreateRecord
{
    protected static string $resource = BankTransferResource::class;

    protected static bool $canCreateAnother = false;
}

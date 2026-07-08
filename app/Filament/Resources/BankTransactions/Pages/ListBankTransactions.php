<?php

namespace App\Filament\Resources\BankTransactions\Pages;

use App\Filament\Exports\BankTransactionExporter;
use App\Filament\Resources\BankTransactions\BankTransactionResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListBankTransactions extends ListRecords
{
    protected static string $resource = BankTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(BankTransactionExporter::class),
            ]),
        ];
    }
}

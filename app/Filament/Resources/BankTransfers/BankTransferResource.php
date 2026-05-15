<?php

namespace App\Filament\Resources\BankTransfers;

use App\Filament\Resources\BankTransfers\Pages\CreateBankTransfer;
use App\Filament\Resources\BankTransfers\Pages\EditBankTransfer;
use App\Filament\Resources\BankTransfers\Pages\ListBankTransfers;
use App\Filament\Resources\BankTransfers\Schemas\BankTransferForm;
use App\Filament\Resources\BankTransfers\Tables\BankTransfersTable;
use App\Models\BankTransfer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BankTransferResource extends Resource
{
    protected static ?string $model = BankTransfer::class;

    protected static ?string $navigationParentItem = 'Banks';

    protected static ?string $navigationLabel = 'Transfers';

    protected static ?int $navigationSort = 260;

    public static function form(Schema $schema): Schema
    {
        return BankTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankTransfersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankTransfers::route('/'),
            'create' => CreateBankTransfer::route('/create'),
            'edit' => EditBankTransfer::route('/{record}/edit'),
        ];
    }
}

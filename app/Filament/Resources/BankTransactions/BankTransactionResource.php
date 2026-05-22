<?php

namespace App\Filament\Resources\BankTransactions;

use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Resources\BankTransactions\Pages\ViewBankTransaction;
use App\Filament\Resources\BankTransactions\Schemas\BankTransactionInfolist;
use App\Filament\Resources\BankTransactions\Tables\BankTransactionsTable;
use App\Filament\Support\PanelAccess;
use App\Models\BankTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationParentItem = 'Banks';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?int $navigationSort = 255;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessFinanceSection();
    }

    public static function infolist(Schema $schema): Schema
    {
        return BankTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankTransactionsTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['bank']);
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
            'index' => ListBankTransactions::route('/'),
            'view' => ViewBankTransaction::route('/{record}'),
        ];
    }
}

<?php

namespace App\Filament\Resources\CashDeposits;

use App\Filament\Resources\CashDeposits\Pages\CreateCashDeposit;
use App\Filament\Resources\CashDeposits\Pages\EditCashDeposit;
use App\Filament\Resources\CashDeposits\Pages\ListCashDeposits;
use App\Filament\Resources\CashDeposits\Pages\ViewCashDeposit;
use App\Filament\Resources\CashDeposits\Schemas\CashDepositForm;
use App\Filament\Resources\CashDeposits\Schemas\CashDepositInfolist;
use App\Filament\Resources\CashDeposits\Tables\CashDepositsTable;
use App\Models\CashDeposit;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashDepositResource extends Resource
{
    protected static ?string $model = CashDeposit::class;

    protected static ?string $navigationParentItem = 'Banks';

    protected static ?string $navigationLabel = 'Cash Deposits';

    protected static ?int $navigationSort = 258;

    public static function form(Schema $schema): Schema
    {
        return CashDepositForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CashDepositInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashDepositsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['bank', 'cashAccount']);
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
            'index' => ListCashDeposits::route('/'),
            'create' => CreateCashDeposit::route('/create'),
            'view' => ViewCashDeposit::route('/{record}'),
            'edit' => EditCashDeposit::route('/{record}/edit'),
        ];
    }
}

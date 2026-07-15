<?php

namespace App\Filament\Resources\Expenses;

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Expenses\Pages\ViewExpense;
use App\Filament\Resources\Expenses\Schemas\ExpenseInfolist;
use App\Filament\Resources\Expenses\Tables\ExpensesTable;
use App\Filament\Support\PanelAccess;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $modelLabel = 'Expense';

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?string $navigationParentItem = 'Payments';

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessFinanceSection();
    }

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

    public static function infolist(Schema $schema): Schema
    {
        return ExpenseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table)
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('transaction_type', [
                PaymentTransactionType::DIRECT_EXPENSE->value,
                PaymentTransactionType::PETTY_CASH_EXPENSE->value,
            ])
            ->with([
                'expenseAccount',
                'expenseTrackingBid',
                'expenseTrackingEmployee',
                'expenseTrackingItem',
                'partner',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'view' => ViewExpense::route('/{record}'),
        ];
    }
}

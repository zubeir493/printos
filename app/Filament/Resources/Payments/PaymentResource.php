<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\RelationManagers\PaymentAllocationsRelationManager;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Filament\Support\PanelAccess;
use App\Models\Payment;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 210;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereRaw('amount > (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE payment_id = payments.id)')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessFinanceSection();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['payment_number', 'partner.name', 'reference'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->payment_number;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Partner' => $record->partner?->name,
            'Amount' => Money::format($record->amount),
            'Date' => $record->payment_date?->format('M j, Y'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['partner']);
    }

    public static function getRelations(): array
    {
        return [
            PaymentAllocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}

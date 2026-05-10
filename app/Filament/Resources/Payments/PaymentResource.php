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
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereRaw('amount > (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE payment_id = payments.id)')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessFinanceSection();
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

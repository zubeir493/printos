<?php

namespace App\Filament\Resources\PaymentAllocations;

use App\Filament\Resources\PaymentAllocations\Pages\ListPaymentAllocations;
use App\Filament\Resources\PaymentAllocations\Pages\ViewPaymentAllocation;
use App\Filament\Resources\PaymentAllocations\Schemas\PaymentAllocationForm;
use App\Filament\Resources\PaymentAllocations\Tables\PaymentAllocationsTable;
use App\Models\PaymentAllocation;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentAllocationResource extends Resource
{
    protected static ?string $model = PaymentAllocation::class;

    protected static ?string $navigationLabel = 'Allocations';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationParentItem(): ?string
    {
        return Filament::getCurrentPanel()?->getId() === 'admin' || 'finance'
            ? 'Payments'
            : null;
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentAllocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentAllocationsTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['payment.partner', 'allocatable']);
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
            'index' => ListPaymentAllocations::route('/'),
            'view' => ViewPaymentAllocation::route('/{record}'),
        ];
    }
}

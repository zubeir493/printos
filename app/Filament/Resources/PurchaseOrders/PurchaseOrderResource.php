<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\RelationManagers\GoodsReceiptsRelationManager;
use App\Filament\Resources\PurchaseOrders\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Filament\Support\PanelAccess;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyPound;

    protected static ?int $navigationSort = 180;

    public static function canCreate(): bool
    {
        return PanelAccess::canManagePurchaseOrders();
    }

    public static function canEdit($record): bool
    {
        return PanelAccess::canManagePurchaseOrders();
    }

    public static function getNavigationBadge(): ?string
    {
        // Count only active purchase orders (exclude received and cancelled)
        $count = static::getModel()::whereNotIn('status', ['received', 'cancelled'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['partner']);
    }

    public static function getRelations(): array
    {
        $relations = [
            GoodsReceiptsRelationManager::class,
        ];

        if (PanelAccess::canAccessFinanceSection()) {
            $relations[] = PaymentsRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }

    public static function updateSubtotal($get, $set)
    {
        // 1. Get all repeater items
        $selectedProducts = $get('purchaseOrderItems') ?? [];

        // 2. Calculate the sum of 'total' fields
        $subtotal = collect($selectedProducts)->reduce(function ($carry, $item) {
            // We recalculate here to be safe, or just use $item['total']
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);

            return $carry + ($qty * $price);
        }, 0);

        // 3. Set the subtotal field outside the repeater
        $set('subtotal', $subtotal);
    }
}

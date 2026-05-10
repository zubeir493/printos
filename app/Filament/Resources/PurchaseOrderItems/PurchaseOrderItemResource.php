<?php

namespace App\Filament\Resources\PurchaseOrderItems;

use App\Filament\Resources\PurchaseOrderItems\Pages\EditPurchaseOrderItem;
use App\Filament\Resources\PurchaseOrderItems\Pages\ListPurchaseOrderItems;
use App\Filament\Resources\PurchaseOrderItems\Pages\ViewPurchaseOrderItem;
use App\Filament\Resources\PurchaseOrderItems\Schemas\PurchaseOrderItemForm;
use App\Filament\Resources\PurchaseOrderItems\Tables\PurchaseOrderItemsTable;
use App\Models\PurchaseOrderItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PurchaseOrderItemResource extends Resource
{
    protected static ?string $model = PurchaseOrderItem::class;

    protected static ?string $navigationLabel = 'Items';

    protected static ?string $navigationParentItem = 'Purchase Orders';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrderItemsTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
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
            'index' => ListPurchaseOrderItems::route('/'),
            'view' => ViewPurchaseOrderItem::route('/{record}'),
            'edit' => EditPurchaseOrderItem::route('/{record}/edit'),
        ];
    }
}

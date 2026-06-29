<?php

namespace App\Filament\Resources\Bids;

use App\Filament\Resources\Bids\Pages\CreateBid;
use App\Filament\Resources\Bids\Pages\EditBid;
use App\Filament\Resources\Bids\Pages\ListBids;
use App\Filament\Resources\Bids\Pages\ViewBid;
use App\Filament\Resources\Bids\Schemas\BidForm;
use App\Filament\Resources\Bids\Tables\BidsTable;
use App\Filament\Support\PanelAccess;
use App\Models\Bid;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BidResource extends Resource
{
    protected static ?string $model = Bid::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Bids';

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessFinanceSection() || PanelAccess::canManageJobOrders();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Bid
            && $record->status === Bid::STATUS_DRAFT
            && static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return BidForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BidsTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBids::route('/'),
            'create' => CreateBid::route('/create'),
            'view' => ViewBid::route('/{record}'),
            'edit' => EditBid::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['bid_number', 'title', 'tender_reference', 'partner.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->bid_number;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Title' => $record->title,
            'Entity' => $record->partner?->name,
            'Value' => Money::format($record->estimated_value),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['partner', 'bidBond', 'performanceBond']);
    }
}

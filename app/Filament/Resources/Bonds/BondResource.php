<?php

namespace App\Filament\Resources\Bonds;

use App\Filament\Resources\Bonds\Pages\ListBonds;
use App\Filament\Resources\Bonds\Pages\ViewBond;
use App\Filament\Resources\Bonds\Schemas\BondInfolist;
use App\Filament\Resources\Bonds\Tables\BondsTable;
use App\Filament\Support\PanelAccess;
use App\Models\Bond;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BondResource extends Resource
{
    protected static ?string $model = Bond::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationParentItem = 'Bids';

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
        return BondInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BondsTable::configure($table)
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['bid.partner', 'issuingPartner', 'bank']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBonds::route('/'),
            'view' => ViewBond::route('/{record}'),
        ];
    }
}

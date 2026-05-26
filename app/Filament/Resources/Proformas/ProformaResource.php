<?php

namespace App\Filament\Resources\Proformas;

use App\Filament\Resources\Proformas\Pages\CreateProforma;
use App\Filament\Resources\Proformas\Pages\EditProforma;
use App\Filament\Resources\Proformas\Pages\ListProformas;
use App\Filament\Resources\Proformas\Pages\ViewProforma;
use App\Filament\Resources\Proformas\Schemas\ProformaForm;
use App\Filament\Resources\Proformas\Tables\ProformasTable;
use App\Filament\Support\PanelAccess;
use App\Models\Proforma;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProformaResource extends Resource
{
    protected static ?string $model = Proforma::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return PanelAccess::canSeeMoneyValues();
    }

    public static function canCreate(): bool
    {
        return PanelAccess::canSeeMoneyValues();
    }

    public static function canEdit($record): bool
    {
        return $record?->status === 'draft';
    }

    public static function form(Schema $schema): Schema
    {
        return ProformaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProformasTable::configure($table);
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
            'index' => ListProformas::route('/'),
            'create' => CreateProforma::route('/create'),
            'view' => ViewProforma::route('/{record}'),
            'edit' => EditProforma::route('/{record}/edit'),
        ];
    }
}

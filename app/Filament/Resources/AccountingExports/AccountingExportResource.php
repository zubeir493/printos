<?php

namespace App\Filament\Resources\AccountingExports;

use App\Filament\Resources\AccountingExports\Pages\ListAccountingExports;
use App\Filament\Resources\AccountingExports\Pages\ViewAccountingExport;
use App\Filament\Resources\AccountingExports\Schemas\AccountingExportInfolist;
use App\Filament\Resources\AccountingExports\Tables\AccountingExportsTable;
use App\Filament\Support\PanelAccess;
use App\Models\AccountingExport;
use App\Models\AccountingIntegration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AccountingExportResource extends Resource
{
    protected static ?string $model = AccountingExport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Peachtree Exports';

    protected static ?string $modelLabel = 'Peachtree export';

    protected static ?string $pluralModelLabel = 'Peachtree exports';

    protected static ?int $navigationSort = 250;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccountingExportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountingExportsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessFinanceSection() && static::isPeachtreeEnabled();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isPeachtreeEnabled();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
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
            'index' => ListAccountingExports::route('/'),
            'view' => ViewAccountingExport::route('/{record}'),
        ];
    }

    private static function isPeachtreeEnabled(): bool
    {
        return (bool) AccountingIntegration::peachtreeDesktop()->enabled;
    }
}

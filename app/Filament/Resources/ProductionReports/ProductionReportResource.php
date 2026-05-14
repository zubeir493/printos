<?php

namespace App\Filament\Resources\ProductionReports;

use App\Filament\Resources\ProductionReports\Pages\EditProductionReport;
use App\Filament\Resources\ProductionReports\Pages\ListProductionReports;
use App\Filament\Resources\ProductionReports\Pages\ViewProductionReport;
use App\Filament\Resources\ProductionReports\Schemas\ProductionReportForm;
use App\Filament\Resources\ProductionReports\Tables\ProductionReportsTable;
use App\Models\ProductionReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductionReportResource extends Resource
{
    protected static ?string $model = ProductionReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    public static function form(Schema $schema): Schema
    {
        return ProductionReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionReportsTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['productionPlan']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return $record?->status === 'draft';
    }

    public static function canDelete($record): bool
    {
        return $record?->status === 'draft';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionReports::route('/'),
            'view' => ViewProductionReport::route('/{record}'),
            'edit' => EditProductionReport::route('/{record}/edit'),
        ];
    }
}

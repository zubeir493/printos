<?php

namespace App\Filament\Resources\CostEstimates;

use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\EditCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\ListCostEstimates;
use App\Filament\Resources\CostEstimates\Pages\ViewCostEstimate;
use App\Filament\Resources\CostEstimates\RelationManagers\CostEstimateLinesRelationManager;
use App\Filament\Resources\CostEstimates\Schemas\CostEstimateForm;
use App\Filament\Resources\CostEstimates\Tables\CostEstimatesTable;
use App\Filament\Support\PanelAccess;
use App\Models\CostEstimate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CostEstimateResource extends Resource
{
    protected static ?string $model = CostEstimate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return PanelAccess::canSeeMoneyValues();
    }

    public static function canEdit($record): bool
    {
        return $record?->isEditable() ?? true;
    }

    public static function form(Schema $schema): Schema
    {
        return CostEstimateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CostEstimatesTable::configure($table)
            ->recordUrl(fn (CostEstimate $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['partner']);
    }

    public static function getRelations(): array
    {
        return [
            CostEstimateLinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCostEstimates::route('/'),
            'create' => CreateCostEstimate::route('/create'),
            'view' => ViewCostEstimate::route('/{record}'),
            'edit' => EditCostEstimate::route('/{record}/edit'),
        ];
    }
}

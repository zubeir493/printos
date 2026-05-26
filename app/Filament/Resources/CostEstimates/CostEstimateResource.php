<?php

namespace App\Filament\Resources\CostEstimates;

use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\EditCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\ListCostEstimates;
use App\Filament\Resources\CostEstimates\Schemas\CostEstimateForm;
use App\Filament\Resources\CostEstimates\Tables\CostEstimatesTable;
use App\Filament\Support\PanelAccess;
use App\Models\CostEstimate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CostEstimateResource extends Resource
{
    protected static ?string $model = CostEstimate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return PanelAccess::canManageJobOrders();
    }

    public static function form(Schema $schema): Schema
    {
        return CostEstimateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CostEstimatesTable::configure($table);
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
            'index' => ListCostEstimates::route('/'),
            'create' => CreateCostEstimate::route('/create'),
            'edit' => EditCostEstimate::route('/{record}/edit'),
        ];
    }
}

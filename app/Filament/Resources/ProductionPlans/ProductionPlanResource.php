<?php

namespace App\Filament\Resources\ProductionPlans;

use App\Filament\Resources\ProductionPlans\Pages\CreateProductionPlan;
use App\Filament\Resources\ProductionPlans\Pages\EditProductionPlan;
use App\Filament\Resources\ProductionPlans\Pages\ListProductionPlans;
use App\Filament\Resources\ProductionPlans\Pages\ViewProductionPlan;
use App\Filament\Resources\ProductionPlans\Schemas\ProductionPlanForm;
use App\Filament\Resources\ProductionPlans\Tables\ProductionPlansTable;
use App\Models\ProductionPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductionPlanResource extends Resource
{
    protected static ?string $model = ProductionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return ProductionPlanForm::configure($schema);
    }

    public static function canEdit($record): bool
    {
        return $record?->status === 'draft';
    }

    public static function canDelete($record): bool
    {
        return $record?->status === 'draft';
    }

    public static function table(Table $table): Table
    {
        return ProductionPlansTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['machines.items']);
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
            'index' => ListProductionPlans::route('/'),
            'create' => CreateProductionPlan::route('/create'),
            'view' => ViewProductionPlan::route('/{record}'),
            'edit' => EditProductionPlan::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\JobOrders;

use App\Filament\Resources\JobOrders\Pages;
use App\Filament\Resources\JobOrders\Pages\CreateJobOrder;
use App\Filament\Resources\JobOrders\Pages\EditJobOrder;
use App\Filament\Resources\JobOrders\Pages\ListJobOrders;
use App\Filament\Resources\JobOrders\Schemas\JobOrderForm;
use App\Filament\Resources\JobOrders\Tables\JobOrdersTable;
use App\Filament\Support\PanelAccess;
use App\Models\JobOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JobOrderResource extends Resource
{
    protected static ?string $model = JobOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function canCreate(): bool
    {
        return PanelAccess::canManageJobOrders();
    }

    public static function canEdit($record): bool
    {
        return PanelAccess::canManageJobOrders();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereNotIn('status', ['completed', 'cancelled'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return JobOrderForm::configure($schema);
    }



    public static function table(Table $table): Table
    {
        return JobOrdersTable::configure($table)
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        $relations = [
            \App\Filament\Resources\JobOrders\RelationManagers\MaterialsOverviewRelationManager::class,
            \App\Filament\Resources\JobOrders\RelationManagers\JobOrderArtworksRelationManager::class,
        ];

        if (PanelAccess::canAccessFinanceSection()) {
            $relations[] = \App\Filament\Resources\JobOrders\RelationManagers\PaymentsRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobOrders::route('/'),
            'create' => CreateJobOrder::route('/create'),
            'view' => Pages\ViewJobOrder::route('/{record}'),
            'edit' => EditJobOrder::route('/{record}/edit'),
        ];
    }
}

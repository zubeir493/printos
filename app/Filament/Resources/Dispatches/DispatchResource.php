<?php

namespace App\Filament\Resources\Dispatches;

use App\Filament\Resources\Dispatches\Pages\CreateDispatch;
use App\Filament\Resources\Dispatches\Pages\EditDispatch;
use App\Filament\Resources\Dispatches\Pages\ListDispatches;
use App\Filament\Resources\Dispatches\Pages\ViewDispatch;
use App\Filament\Resources\Dispatches\Schemas\DispatchForm;
use App\Filament\Resources\Dispatches\Tables\DispatchesTable;
use App\Filament\Support\PanelAccess;
use App\Models\Dispatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DispatchResource extends Resource
{
    protected static ?string $model = Dispatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 80;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereNotIn('status', ['completed', 'cancelled'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return DispatchForm::configure($schema);
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessWarehouseSection();
    }

    public static function table(Table $table): Table
    {
        return DispatchesTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['jobOrder.partner']);
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
            'index' => ListDispatches::route('/'),
            'create' => CreateDispatch::route('/create'),
            'view' => ViewDispatch::route('/{record}'),
            'edit' => EditDispatch::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['jobOrder.job_order_number', 'jobOrder.partner.name', 'status'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->jobOrder?->job_order_number ?? "Dispatch #{$record->id}";
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Partner' => $record->jobOrder?->partner?->name,
            'Status' => ucfirst($record->status),
            'Date' => $record->delivery_date?->format('M j, Y'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        if (! PanelAccess::canAccessWarehouseSection()) {
            return static::getModel()::query()->whereRaw('1 = 0');
        }

        return parent::getGlobalSearchEloquentQuery()
            ->with(['jobOrder.partner']);
    }
}

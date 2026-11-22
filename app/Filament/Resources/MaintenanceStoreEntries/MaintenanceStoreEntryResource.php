<?php

namespace App\Filament\Resources\MaintenanceStoreEntries;

use App\Filament\Resources\MaintenanceStoreEntries\Actions\MaintenanceStoreActions;
use App\Filament\Resources\MaintenanceStoreEntries\Pages\ManageMaintenanceStoreEntries;
use App\Filament\Support\PanelAccess;
use App\Models\MaintenanceStoreEntry;
use App\Support\WarehouseAccess;
use App\UserRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class MaintenanceStoreEntryResource extends Resource
{
    protected static ?string $model = MaintenanceStoreEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Maintenance store';

    protected static ?string $slug = 'maintenance-store';

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessWarehouseSection()
            && in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Warehouse], true);
    }

    public static function getEloquentQuery(): Builder
    {
        return WarehouseAccess::scope(parent::getEloquentQuery(), 'maintenance_store_entries.warehouse_id')
            ->with(['inventoryItem', 'warehouse', 'employee', 'machine']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Recorded')->dateTime()->sortable(),
                TextColumn::make('inventoryItem.name')->label('Item')
                    ->description(fn (MaintenanceStoreEntry $record): string => $record->inventoryItem->sku)
                    ->searchable(['name', 'sku']),
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state): string => self::typeOptions()[$state]),
                TextColumn::make('warehouse.name')->label('Warehouse')->sortable(),
                TextColumn::make('employee.full_name')->label('Checked out to')->placeholder('—'),
                TextColumn::make('machine.name')->label('Machine')->placeholder('—'),
                TextColumn::make('quantity')->label('Quantity')
                    ->formatStateUsing(fn (string $state, MaintenanceStoreEntry $record): string => self::quantityWithUnit((float) $state, $record)),
                TextColumn::make('outstanding')->label('Still out')
                    ->state(fn (MaintenanceStoreEntry $record): ?string => $record->type === MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT
                        ? self::quantityWithUnit($record->outstandingQuantity(), $record)
                        : null)
                    ->description(fn (MaintenanceStoreEntry $record): ?string => $record->type === MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT
                        && (float) $record->returned_quantity > 0
                        ? self::quantityWithUnit((float) $record->returned_quantity, $record).' returned'
                        : null)
                    ->placeholder('—'),
                TextColumn::make('due_date')->label('Due back')->date()->sortable()
                    ->description(fn (MaintenanceStoreEntry $record): ?string => $record->due_date?->lt(today()) && $record->outstandingQuantity() > 0 ? 'Overdue' : null)
                    ->placeholder('—'),
                TextColumn::make('reference')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->limit(65)->tooltip(fn (MaintenanceStoreEntry $record): ?string => $record->notes)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(self::typeOptions()),
                SelectFilter::make('warehouse_id')->label('Warehouse')
                    ->options(fn () => WarehouseAccess::warehouses()->orderBy('name')->pluck('name', 'id')),
                Filter::make('outstanding')->label('Tools still out')
                    ->query(fn (Builder $query): Builder => $query->where('type', MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT)
                        ->whereColumn('returned_quantity', '<', 'quantity')),
            ])
            ->recordActions([
                MaintenanceStoreActions::returnTool(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No maintenance stock activity')
            ->emptyStateDescription('Tool checkouts and spare-part issues will appear here.');
    }

    private static function quantityWithUnit(float $quantity, MaintenanceStoreEntry $record): string
    {
        return trim(Number::format($quantity, maxPrecision: 2).' '.$record->inventoryItem->unit);
    }

    /** @return array<string, string> */
    private static function typeOptions(): array
    {
        return [
            MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT => 'Tool checkout',
            MaintenanceStoreEntry::TYPE_SPARE_PART_ISSUE => 'Spare-part issue',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMaintenanceStoreEntries::route('/'),
        ];
    }
}

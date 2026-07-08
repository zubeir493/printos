<?php

namespace App\Filament\Pages;

use App\Filament\Exports\InventoryBalanceExporter;
use App\Filament\Widgets\StockOverviewStats;
use App\Models\InventoryBalance;
use App\Models\Warehouse;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class StockOverview extends Page implements HasForms, HasTable
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 65;

    protected string $view = 'filament.pages.stock-overview';

    use Forms\Concerns\InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    public ?int $warehouse_id = null;

    public function mount(): void
    {
        $this->warehouse_id = Warehouse::where('is_default', true)->value('id');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StockOverviewStats::make([
                'warehouse_id' => $this->warehouse_id,
            ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(InventoryBalanceExporter::class),
            ]),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(Warehouse::pluck('name', 'id'))
                    ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                    ->required()
                    ->live(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {

                if (! $this->warehouse_id) {
                    return InventoryBalance::query()->whereRaw('1 = 0');
                }

                return InventoryBalance::query()
                    ->with('inventoryItem')
                    ->where('warehouse_id', $this->warehouse_id)
                    ->where('quantity_on_hand', '>', 0);
            })
            ->columns([
                TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity_on_hand')
                    ->label('Quantity')
                    ->formatStateUsing(function ($state, $record) {
                        $item = $record->inventoryItem;

                        if (! $item) {
                            return number_format($state);
                        }

                        if ($item->hasPurchaseUnit()) {
                            return number_format($item->toPurchaseUnits((float) $state), 2).' '.$item->purchase_unit;
                        }

                        return number_format($state).' '.$item->unit;
                    })
                    ->sortable(),

                TextColumn::make('total_value')
                    ->state(function ($record) {
                        $item = $record->inventoryItem;
                        if (! $item || in_array($item->type, ['tools', 'spare_parts', 'wip'])) {
                            return null;
                        }

                        // base-unit cost: average_cost if available, else price / factor for raw materials
                        $baseUnitCost = $item->hasPurchaseUnit()
                            ? ((float) ($item->average_cost ?? 0) > 0
                                ? (float) $item->average_cost
                                : (float) ($item->price ?? 0) / (float) $item->conversion_factor)
                            : (float) ($item->average_cost > 0 ? $item->average_cost : ($item->price ?? 0));

                        return (float) $record->quantity_on_hand * $baseUnitCost;
                    })
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state === null ? null : Money::format($state))
                    ->color('success')
                    ->label('Total Value')
                    ->summarize(
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('Warehouse Value')
                            ->using(
                                fn ($query) => (float) $query->join('inventory_items', 'inventory_items.id', '=', 'inventory_balances.inventory_item_id')
                                    ->sum(DB::raw('
                                        inventory_balances.quantity_on_hand * (
                                            CASE
                                                WHEN inventory_items.type IN ("tools", "spare_parts", "wip") THEN 0
                                                WHEN inventory_items.type = "raw_material" AND inventory_items.conversion_factor > 0
                                                    THEN COALESCE(
                                                        NULLIF(CAST(inventory_items.average_cost AS DECIMAL(15,4)), 0),
                                                        CAST(inventory_items.price AS DECIMAL(15,4)) / CAST(inventory_items.conversion_factor AS DECIMAL(15,4))
                                                    )
                                                ELSE COALESCE(
                                                    NULLIF(CAST(inventory_items.average_cost AS DECIMAL(15,4)), 0),
                                                    CAST(inventory_items.price AS DECIMAL(15,4))
                                                )
                                            END
                                        )
                                    '))
                            )
                            ->formatStateUsing(fn ($state) => Money::format($state))
                    ),
            ])
            ->emptyStateHeading('Selected warehouse is empty')
            ->emptyStateDescription('This warehouse currently has no inventory.')
            ->defaultSort('inventoryItem.name');
    }
}

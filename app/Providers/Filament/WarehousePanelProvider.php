<?php

namespace App\Providers\Filament;

use App\Filament\Pages\StockOverview;
use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Resources\MaterialRequests\MaterialRequestResource;
use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Filament\Warehouse\Widgets\LogisticsPulseChart;
use App\Filament\Warehouse\Widgets\PendingPickListTable;
use App\Filament\Warehouse\Widgets\ReceivingDiscrepancies;
use App\Filament\Warehouse\Widgets\RecentStockMovementsTable;
use App\Filament\Warehouse\Widgets\WarehouseHealthStats;
use App\Filament\Warehouse\Widgets\WarehousePanelStats;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class WarehousePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('warehouse')
            ->path('warehouse')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->databaseNotifications()
            ->colors([
                'primary' => Color::Teal,
            ])
            ->resources([
                InventoryItemResource::class,
                WarehouseResource::class,
                GoodsReceiptResource::class,
                DispatchResource::class,
                StockMovementResource::class,
                StockTransferResource::class,
                StockAdjustmentResource::class,
                MaterialRequestResource::class,
            ])
            ->pages([
                Dashboard::class,
                StockOverview::class,
            ])
            ->widgets([
                WarehousePanelStats::class,
                WarehouseHealthStats::class,
                LogisticsPulseChart::class,
                ReceivingDiscrepancies::class,
                RecentStockMovementsTable::class,
                PendingPickListTable::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\PrimaryColorAvatarProvider;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\StockOverview;
use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Resources\MaterialRequests\MaterialRequestResource;
use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Filament\Warehouse\Widgets\LogisticsActivityWidget;
use App\Filament\Warehouse\Widgets\StockMovementPulseWidget;
use App\Filament\Warehouse\Widgets\WarehouseHealthStats;
use App\Filament\Warehouse\Widgets\WipAvailabilityWidget;
use Filament\Enums\ThemeMode;
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
            ->authGuard('web')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Plus Jakarta Sans')
            ->darkMode(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->spa()
            ->databaseNotifications()
            ->defaultAvatarProvider(PrimaryColorAvatarProvider::class)
            ->profile(EditProfile::class)
            ->brandLogo(asset('images/logo.svg'))
            ->favicon(asset('images/favicon.svg'))
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => Color::Indigo,
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
                WarehouseHealthStats::class,
                LogisticsActivityWidget::class,
                WipAvailabilityWidget::class,
                StockMovementPulseWidget::class,
            ])
            ->globalSearch(true)
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
            ])
            ->plugins([
                //
            ]);
    }
}

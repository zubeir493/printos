<?php

namespace App\Providers\Filament;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Retail\Widgets\RetailCounterStats;
use App\Filament\Retail\Widgets\RetailDemandChart;
use App\Filament\Retail\Widgets\RetailReorderTable;
use App\Filament\Retail\Widgets\RetailStockHealthStats;
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

class RetailPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('retail')
            ->path('retail')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->databaseNotifications()
            ->colors([
                'primary' => Color::Rose,
            ])
            ->resources([
                SalesOrderResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                RetailCounterStats::class,
                RetailDemandChart::class,
                RetailStockHealthStats::class,
                RetailReorderTable::class,
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

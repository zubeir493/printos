<?php

namespace App\Providers\Filament;

use App\Filament\Production\Widgets\FloorEfficiencyStats;
use App\Filament\Production\Widgets\MachineEfficiencyChart;
use App\Filament\Production\Widgets\MaterialShortageWarnings;
use App\Filament\Production\Widgets\TodaysProductionScheduleTable;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Filament\Resources\Machines\MachineResource;
use App\Filament\Resources\MaterialRequests\MaterialRequestResource;
use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use App\Filament\Resources\ProductionReports\ProductionReportResource;
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

class ProductionPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('production')
            ->path('production')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(false)
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Light)
            ->databaseNotifications()
            ->brandLogo(asset('images/logo.svg')) // TODO: Place logo in public/images/logo.svg
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->resources([
                JobOrderTaskResource::class,
                ProductionPlanResource::class,
                ProductionReportResource::class,
                MachineResource::class,
                MaterialRequestResource::class,
                JobOrderResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Production/Pages'), for: 'App\Filament\Production\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                FloorEfficiencyStats::class,
                MachineEfficiencyChart::class,
                MaterialShortageWarnings::class,
                TodaysProductionScheduleTable::class,
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

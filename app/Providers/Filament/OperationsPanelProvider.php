<?php

namespace App\Providers\Filament;

use App\Filament\Operations\Widgets\HighPriorityJobsTable;
use App\Filament\Operations\Widgets\OperationsJobStatusChart;
use App\Filament\Operations\Widgets\OperationsPanelStats;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Filament\Resources\MaterialIssueApprovals\MaterialIssueApprovalResource;
use App\Filament\Resources\MaterialRequests\MaterialRequestResource;
use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use App\Filament\Resources\ProductionReports\ProductionReportResource;
use App\Filament\Resources\PurchaseOrderItems\PurchaseOrderItemResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
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

class OperationsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operations')
            ->path('operations')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(false)
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Light)
            ->databaseNotifications()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->resources([
                ArtworkResource::class,
                DispatchResource::class,
                EmailLogResource::class,
                GoodsReceiptResource::class,
                PartnerResource::class,
                JobOrderResource::class,
                JobOrderTaskResource::class,
                MaterialRequestResource::class,
                MaterialIssueApprovalResource::class,
                PurchaseOrderResource::class,
                PurchaseOrderItemResource::class,
                ProductionPlanResource::class,
                ProductionReportResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Operations/Pages'), for: 'App\Filament\Operations\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                OperationsPanelStats::class,
                OperationsJobStatusChart::class,
                HighPriorityJobsTable::class,
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

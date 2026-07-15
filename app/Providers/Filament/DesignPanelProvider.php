<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\PrimaryColorAvatarProvider;
use App\Filament\Design\Widgets\ArtworkPipelineWidget;
use App\Filament\Design\Widgets\DesignCompletionRateWidget;
use App\Filament\Design\Widgets\DesignQueueWidget;
use App\Filament\Design\Widgets\DesignSLAStats;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Filament\Resources\Partners\PartnerResource;
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

class DesignPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('design')
            ->path('design')
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
                ArtworkResource::class,
                JobOrderResource::class,
                JobOrderTaskResource::class,
                EmailLogResource::class,
                PartnerResource::class,

            ])
            ->discoverPages(in: app_path('Filament/Design/Pages'), for: 'App\Filament\Design\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                DesignSLAStats::class,
                ArtworkPipelineWidget::class,
                DesignQueueWidget::class,
                DesignCompletionRateWidget::class,
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
            ])
            ->plugins([
                //
            ]);
    }
}

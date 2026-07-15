<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\PrimaryColorAvatarProvider;
use App\Filament\Hr\Widgets\AttendanceActivityWidget;
use App\Filament\Hr\Widgets\HrPanelStats;
use App\Filament\Hr\Widgets\LeaveApprovalRateWidget;
use App\Filament\Hr\Widgets\WorkforceMixWidget;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveTypes\LeaveTypeResource;
use App\Filament\Resources\Shifts\ShiftResource;
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

class HrPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('hr')
            ->path('hr')
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
                AttendanceSegmentResource::class,
                EmployeeResource::class,
                HolidayResource::class,
                LeaveRequestResource::class,
                LeaveTypeResource::class,
                ShiftResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                HrPanelStats::class,
                WorkforceMixWidget::class,
                AttendanceActivityWidget::class,
                LeaveApprovalRateWidget::class,
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

<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\PrimaryColorAvatarProvider;
use App\Filament\Hr\Widgets\HrPanelStats;
use App\Filament\Hr\Widgets\SalaryRevisionHistoryTable;
use App\Filament\Hr\Widgets\WorkforceCompositionChart;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Filament\Resources\EmployeeLoans\EmployeeLoanResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveTypes\LeaveTypeResource;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
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
            ->font('Albert Sans')
            ->darkMode(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->databaseNotifications()
            ->defaultAvatarProvider(PrimaryColorAvatarProvider::class)
            ->profile(EditProfile::class)
            ->brandLogo(asset('images/logo.svg')) // TODO: Place logo in public/images/logo.svg
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->resources([
                AttendanceImportResource::class,
                AttendanceSegmentResource::class,
                EmployeeLoanResource::class,
                EmployeeResource::class,
                HolidayResource::class,
                LeaveRequestResource::class,
                LeaveTypeResource::class,
                PayrollRunResource::class,
                ShiftResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                HrPanelStats::class,
                WorkforceCompositionChart::class,
                SalaryRevisionHistoryTable::class,
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

<?php

namespace App\Filament\Resources\PayPolicies\Pages;

use App\Filament\Pages\Settings;
use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Filament\Resources\PayPolicies\PayPolicyResource;
use App\UserRole;
use Filament\Resources\Pages\ManageRecords;

class ManagePayPolicies extends ManageRecords
{
    public function mount(): void
    {
        parent::mount();
        $this->redirect(Settings::canAccess()
            ? Settings::getUrl(panel: auth()->user()?->role === UserRole::Finance ? 'finance' : 'admin', parameters: ['section' => 'payroll'])
            : AttendanceSegmentResource::getUrl());
    }

    protected static string $resource = PayPolicyResource::class;
}

<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Models\AttendanceSegment;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class AttendanceDevice extends Page
{
    protected string $view = 'filament.pages.attendance-device';

    protected static ?string $navigationLabel = 'Attendance device';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 312;

    public function mount(): void
    {
        $this->redirect(AttendanceSegmentResource::getUrl());
    }

    public static function canAccess(): bool
    {
        return Gate::allows('create', AttendanceSegment::class);
    }
}

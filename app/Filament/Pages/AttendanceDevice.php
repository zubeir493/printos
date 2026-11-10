<?php

namespace App\Filament\Pages;

use App\Jobs\SyncDeviceAttendance;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class AttendanceDevice extends Page
{
    protected string $view = 'filament.pages.attendance-device';

    protected static ?string $navigationLabel = 'Attendance device';

    protected static ?int $navigationSort = 312;

    public static function canAccess(): bool
    {
        return Gate::allows('create', AttendanceSegment::class);
    }

    public function infolist(Schema $schema): Schema
    {
        $state = AttendanceDeviceSync::where('device_id', config('attendance_device.id'))->first();
        $connection = 'Unknown — no successful download yet';

        if ($state?->last_connected_at) {
            $connection = $state->last_connected_at->lt(now()->subMinutes(10)) ? 'Stale — last connection over 10 minutes ago' : 'Connected at last download';
        }

        if ($state?->status === 'failed') {
            $connection = 'Last synchronization failed';
        }

        return $schema->state([
            'device' => config('attendance_device.host').':'.config('attendance_device.port').' ('.config('attendance_device.transport').')',
            'enabled' => config('attendance_device.enabled') ? 'Enabled — every 5 minutes' : 'Disabled — verify local preview and shift setup before enabling',
            'processing' => config('attendance_device.processing_enabled') ? 'Enabled — using assigned shifts' : 'Paused — punches are retained while shift policy is reviewed',
            'connection' => $connection,
            'status' => $state?->status ?? 'never_synced',
            'last_attempt' => $state?->last_attempt_at?->toDateTimeString() ?? 'Never',
            'last_download' => $state?->last_download_at?->toDateTimeString() ?? 'Never',
            'last_success' => $state?->last_successful_sync_at?->toDateTimeString() ?? 'Never',
            'last_error' => $state?->last_error ?? 'None',
            'pending' => AttendanceDevicePunch::where('device_id', config('attendance_device.id'))->whereNull('processed_at')->count(),
            'collected' => AttendanceDevicePunch::where('device_id', config('attendance_device.id'))->count(),
            'unmatched' => AttendanceDevicePunch::where('device_id', config('attendance_device.id'))
                ->whereNotIn('device_user_id', Employee::whereNotNull('attendance_device_id')->select('attendance_device_id'))
                ->distinct()->count('device_user_id'),
            'clock_warning' => AttendanceDevicePunch::where('device_id', config('attendance_device.id'))->where('punched_at', '>', now()->addMinutes(5))->exists()
                ? 'Some recorded punches are ahead of ERP time. Original timestamps are retained; verify the device clock before processing.' : 'None',
            'review' => AttendanceDevicePunch::where('device_id', config('attendance_device.id'))->whereNotNull('processing_error')->select('device_user_id', 'processing_error')->distinct()->limit(20)->get()
                ->map(fn (AttendanceDevicePunch $punch): string => 'FP No '.$punch->device_user_id.': '.$punch->processing_error)->implode("\n") ?: 'None',
        ])->components([
            TextEntry::make('device'),
            TextEntry::make('enabled')->label('Automatic synchronization'),
            TextEntry::make('processing')->label('Attendance processing'),
            TextEntry::make('connection')->label('Connection status'),
            TextEntry::make('status')->badge(),
            TextEntry::make('last_attempt'),
            TextEntry::make('last_download'),
            TextEntry::make('last_success')->label('Last successful collection'),
            TextEntry::make('collected')->label('Collected punches'),
            TextEntry::make('unmatched')->label('Unmatched AC No./FP No. values'),
            TextEntry::make('pending')->label('Punches pending processing'),
            TextEntry::make('last_error')->columnSpanFull(),
            TextEntry::make('clock_warning')->label('Device clock')->columnSpanFull(),
            TextEntry::make('review')->label('Review required (first 20 issues)')->columnSpanFull(),
        ])->columns(2);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncNow')
                ->label('Sync Now')
                ->authorize(fn (): bool => static::canAccess())
                ->disabled(fn (): bool => ! config('attendance_device.enabled'))
                ->action(function (): void {
                    Gate::authorize('create', AttendanceSegment::class);

                    if (! config('attendance_device.enabled')) {
                        return;
                    }

                    SyncDeviceAttendance::dispatch();
                    Notification::make()->title('Synchronization queued')->body('The status updates when the queue worker processes the job.')->success()->send();
                }),
        ];
    }
}

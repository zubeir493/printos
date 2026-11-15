<?php

namespace App\Services\Hr;

use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Models\Employee;
use App\Support\NotificationRecipients;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotifyUnmatchedAttendanceNumbers
{
    /** @return Collection<int, string> */
    public static function unmatchedIds(string $deviceId): Collection
    {
        return AttendanceDevicePunch::where('device_id', $deviceId)->whereNull('processed_at')
            ->whereNotIn('device_user_id', Employee::whereNotNull('attendance_device_id')->select('attendance_device_id'))
            ->distinct()->orderBy('device_user_id')->pluck('device_user_id')->map(fn ($id): string => (string) $id);
    }

    public function handle(string $deviceId): void
    {
        DB::transaction(function () use ($deviceId): void {
            $state = AttendanceDeviceSync::firstOrCreate(['device_id' => $deviceId]);
            $state = AttendanceDeviceSync::whereKey($state->id)->lockForUpdate()->firstOrFail();
            $ids = self::unmatchedIds($deviceId);
            $newIds = $ids->diff($state->notified_unmatched_ids ?? []);
            $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::HR);
            if ($newIds->isNotEmpty() && $recipients->isNotEmpty()) {
                $body = 'Unmatched FP numbers: '.$newIds->take(10)->join(', ').($newIds->count() > 10 ? ' and '.($newIds->count() - 10).' more.' : '.').' Open Attendance → Sync for the full list.';
                foreach ($recipients as $recipient) {
                    $recipient->notifyNow(Notification::make()->title('Attendance employee mapping needed')->warning()->body($body)
                        ->actions([Action::make('attendance')->label('View attendance')
                            ->url(AttendanceSegmentResource::getUrl('index', isAbsolute: false, panel: $recipient->role === UserRole::Admin ? 'admin' : 'hr'))])
                        ->toDatabase());
                }
            }
            if ($newIds->isEmpty() || $recipients->isNotEmpty()) {
                $state->update(['notified_unmatched_ids' => $ids->values()->all()]);
            }
        }, 3);
    }
}

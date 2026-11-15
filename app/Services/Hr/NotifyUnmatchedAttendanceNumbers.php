<?php

namespace App\Services\Hr;

use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Models\Employee;
use App\Models\User;
use App\Support\NotificationRecipients;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotifyUnmatchedAttendanceNumbers
{
    /** @return Collection<int, AttendanceDevicePunch> */
    public static function unmatchedRows(string $deviceId): Collection
    {
        return AttendanceDevicePunch::where('device_id', $deviceId)->whereNull('processed_at')
            ->whereNotIn('device_user_id', Employee::whereNotNull('attendance_device_id')->select('attendance_device_id'))
            ->selectRaw('device_user_id, COUNT(*) AS punch_count, MAX(punched_at) AS last_punch')
            ->groupBy('device_user_id')->orderBy('device_user_id')->get();
    }

    public static function notificationUrl(User $recipient): string
    {
        return AttendanceSegmentResource::getUrl('index', ['action' => 'unmatchedNumbers'], panel: $recipient->role === UserRole::Admin ? 'admin' : 'hr');
    }

    public function repairExistingNotifications(): int
    {
        $repaired = 0;
        foreach (NotificationRecipients::roles(UserRole::Admin, UserRole::HR) as $recipient) {
            foreach ($recipient->notifications()->where('data->title', 'Attendance employee mapping needed')->get() as $notification) {
                $data = $notification->data;
                foreach ($data['actions'] ?? [] as $index => $action) {
                    if (($action['name'] ?? null) === 'attendance') {
                        $data['actions'][$index]['url'] = self::notificationUrl($recipient);
                        $data['actions'][$index]['label'] = 'Review FP numbers';
                    }
                }
                $data['body'] = str($data['body'] ?? '')->before(' Open Attendance')->toString();
                if ($data !== $notification->data) {
                    $notification->update(['data' => $data]);
                    $repaired++;
                }
            }
        }

        return $repaired;
    }

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
                $body = 'Unmatched FP numbers: '.$newIds->take(10)->join(', ').($newIds->count() > 10 ? ' and '.($newIds->count() - 10).' more.' : '.');
                foreach ($recipients as $recipient) {
                    $recipient->notifyNow(Notification::make()->title('Attendance employee mapping needed')->warning()->body($body)
                        ->actions([Action::make('attendance')->label('Review FP numbers')->url(self::notificationUrl($recipient))])
                        ->toDatabase());
                }
            }
            if ($newIds->isEmpty() || $recipients->isNotEmpty()) {
                $state->update(['notified_unmatched_ids' => $ids->values()->all()]);
            }
        }, 3);
    }
}

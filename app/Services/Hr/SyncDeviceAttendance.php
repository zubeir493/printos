<?php

namespace App\Services\Hr;

use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncDeviceAttendance
{
    public function __construct(private ReadZktecoAttendance $reader, private ProcessDeviceAttendance $processor) {}

    public function handle(): void
    {
        if (! config('attendance_device.enabled')) {
            return;
        }

        $deviceId = (string) config('attendance_device.id');
        $lock = Cache::lock('attendance-device:'.$deviceId, 900);

        if (! $lock->get()) {
            throw new RuntimeException('Attendance device synchronization is already running.');
        }

        try {
            $state = AttendanceDeviceSync::firstOrCreate(['device_id' => $deviceId]);
            $state->update(['status' => 'syncing', 'last_attempt_at' => now()]);

            try {
                $rows = $this->reader->handle();
                $state->update(['last_connected_at' => now()]);

                DB::transaction(function () use ($rows, $deviceId): void {
                    $batch = [];
                    foreach ($rows as $row) {
                        $userId = (string) ($row['user_id'] ?? '');
                        $timestamp = (string) ($row['timestamp'] ?? '');
                        $direction = filter_var($row['raw_direction'] ?? null, FILTER_VALIDATE_INT);

                        if ($userId === '' || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $timestamp) || $direction === false || $direction < 0 || $direction > 255) {
                            throw new RuntimeException('The device returned an invalid attendance record. No download checkpoint was advanced.');
                        }

                        $time = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $timestamp, config('attendance_device.timezone'));

                        if ($time->format('Y-m-d H:i:s') !== $timestamp) {
                            throw new RuntimeException('The device returned an invalid timestamp. Verify its clock and timezone.');
                        }

                        $fingerprint = hash('sha256', implode('|', [$deviceId, $userId, $timestamp, $direction]));
                        $batch[$fingerprint] = [
                            'fingerprint' => $fingerprint,
                            'device_id' => $deviceId,
                            'device_user_id' => $userId,
                            'punched_at' => $time->setTimezone(config('app.timezone'))->toDateTimeString(),
                            'direction' => (int) $row['raw_direction'],
                            'raw_data' => json_encode($row, JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (count($batch) === 100) {
                            DB::table('attendance_device_punches')->upsert(array_values($batch), ['fingerprint'], ['fingerprint']);
                            $batch = [];
                        }
                    }

                    if ($batch !== []) {
                        DB::table('attendance_device_punches')->upsert(array_values($batch), ['fingerprint'], ['fingerprint']);
                    }
                });

                $state->update(['last_download_at' => now(), 'last_successful_sync_at' => now(), 'fetched_count' => count($rows)]);
                $this->processor->handle($deviceId);
                $pending = AttendanceDevicePunch::where('device_id', $deviceId)->whereNull('processed_at')->count();
                $error = AttendanceDevicePunch::where('device_id', $deviceId)->whereNotNull('processing_error')->value('processing_error');
                $updates = [
                    'status' => $error ? 'needs_review' : (config('attendance_device.processing_enabled') ? 'completed' : 'collected'),
                    'pending_count' => $pending,
                    'last_error' => $error,
                ];

                $state->update($updates);
                Log::info('Attendance device synchronization completed.', ['device_id' => $deviceId, 'fetched' => count($rows), 'pending' => $pending]);
            } catch (Throwable $exception) {
                $state->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
                Log::error('Attendance device synchronization failed.', ['device_id' => $deviceId, 'error' => $exception->getMessage()]);

                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }
}

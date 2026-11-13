<?php

namespace App\Jobs;

use App\Models\AttendanceDeviceSync;
use App\Services\Hr\SyncDeviceAttendance as Synchronizer;
use App\Support\AttendanceDeviceSettings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncDeviceAttendance implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 900;

    public function __construct()
    {
        $this->onConnection(config('attendance_device.queue_connection'));
        $this->onQueue(config('attendance_device.queue'));
    }

    /**
     * Execute the job.
     */
    public function handle(Synchronizer $synchronizer): void
    {
        $synchronizer->handle();
    }

    public function uniqueId(): string
    {
        return (string) AttendanceDeviceSettings::get('id');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function failed(?Throwable $exception): void
    {
        AttendanceDeviceSync::updateOrCreate(['device_id' => AttendanceDeviceSettings::get('id')], [
            'status' => 'failed',
            'last_error' => $exception?->getMessage() ?? 'Attendance sync job failed or timed out.',
        ]);
    }
}

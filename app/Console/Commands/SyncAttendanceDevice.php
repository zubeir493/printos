<?php

namespace App\Console\Commands;

use App\Jobs\SyncDeviceAttendance as SyncJob;
use App\Models\AttendanceDeviceSync;
use App\Services\Hr\SyncDeviceAttendance;
use Illuminate\Console\Command;
use Throwable;

class SyncAttendanceDevice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:device-sync {--inline : Run immediately instead of queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect biometric attendance through the ERP synchronization pipeline';

    /**
     * Execute the console command.
     */
    public function handle(SyncDeviceAttendance $synchronizer): int
    {
        if (! config('attendance_device.enabled')) {
            $this->error('Attendance device synchronization is disabled. Verify the read-only preview before enabling it.');

            return self::FAILURE;
        }

        try {
            if (! $this->option('inline')) {
                SyncJob::dispatch();
                $this->info('Attendance synchronization queued on '.config('attendance_device.queue').'.');

                return self::SUCCESS;
            }

            $synchronizer->handle();
            $state = AttendanceDeviceSync::where('device_id', config('attendance_device.id'))->firstOrFail();
            $this->info('Collection completed: '.$state->fetched_count.' punches read; '.$state->pending_count.' pending attendance processing.');

            if ($state->last_error) {
                $this->warn($state->last_error);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

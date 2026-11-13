<?php

namespace App\Console\Commands;

use App\Jobs\SyncDeviceAttendance as SyncJob;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Services\Hr\ProcessDeviceAttendance;
use App\Services\Hr\SyncDeviceAttendance;
use App\Support\AttendanceDeviceSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class SyncAttendanceDevice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:device-sync {--inline : Run immediately instead of queueing} {--stored : Process stored punches without connecting to the device}';

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
        if ($this->option('stored')) {
            return $this->processStoredPunches();
        }

        if (! AttendanceDeviceSettings::get('enabled')) {
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
            $state = AttendanceDeviceSync::where('device_id', AttendanceDeviceSettings::get('id'))->firstOrFail();
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

    private function processStoredPunches(): int
    {
        $settings = AttendanceDeviceSettings::all();
        if (! $settings['processing_enabled']) {
            $this->error('Attendance importing is paused. Enable Import attendance in device settings.');

            return self::FAILURE;
        }

        $deviceId = (string) $settings['id'];
        $lock = Cache::lock('attendance-device:'.$deviceId, 900);
        try {
            if (! $lock->get()) {
                throw new RuntimeException('Attendance device synchronization is already running.');
            }
            $pendingBefore = AttendanceDevicePunch::where('device_id', $deviceId)->whereNull('processed_at')->count();
            app(ProcessDeviceAttendance::class)->handle($deviceId, $settings);
            $pending = AttendanceDevicePunch::where('device_id', $deviceId)->whereNull('processed_at')->count();
            AttendanceDeviceSync::where('device_id', $deviceId)->update(['pending_count' => $pending]);
            $this->info('Processing completed: '.($pendingBefore - $pending).' punches processed; '.$pending.' pending.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}

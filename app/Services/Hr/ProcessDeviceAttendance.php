<?php

namespace App\Services\Hr;

use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceLog;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessDeviceAttendance
{
    public function __construct(private RebuildAttendanceDailySummaries $summaries) {}

    public function handle(string $deviceId): void
    {
        $userIds = AttendanceDevicePunch::where('device_id', $deviceId)->whereNull('processed_at')->distinct()->pluck('device_user_id');

        foreach ($userIds as $userId) {
            $punches = AttendanceDevicePunch::where('device_id', $deviceId)->where('device_user_id', $userId)->orderBy('punched_at')->orderBy('id')->get();
            $employee = Employee::with('attendanceShift')->where('attendance_device_id', $userId)->first();

            if (! $employee) {
                $this->setError($punches->whereNull('processed_at'), 'No employee matches device user_id / FP No '.$userId.'.');

                continue;
            }

            if (! config('attendance_device.processing_enabled')) {
                $this->setError($punches->whereNull('processed_at'), null);

                continue;
            }

            $shift = $employee->attendanceShift ?? Shift::find(config('attendance_device.default_shift_id'));

            if (! $shift) {
                $this->setError($punches->whereNull('processed_at'), 'Assign a biometric attendance shift to employee '.$employee->employee_id.'.');

                continue;
            }

            $punches->groupBy(fn (AttendanceDevicePunch $punch): string => $this->workDate($punch, $shift))
                ->each(function (Collection $dayPunches, string $date) use ($deviceId, $employee, $shift): void {
                    if ($dayPunches->whereNull('processed_at')->isEmpty()) {
                        return;
                    }

                    try {
                        DB::transaction(fn () => $this->processDay($deviceId, $employee, $shift, $date, $dayPunches));
                    } catch (DomainException $exception) {
                        $this->setError($dayPunches->whereNull('processed_at'), $exception->getMessage());
                    }
                });
        }
    }

    private function workDate(AttendanceDevicePunch $punch, Shift $shift): string
    {
        $time = $punch->punched_at->setTimezone(config('attendance_device.timezone'));

        if ($shift->end_time <= $shift->start_time) {
            $start = CarbonImmutable::parse($time->toDateString().' '.$shift->start_time, $time->timezone);
            $end = CarbonImmutable::parse($time->toDateString().' '.$shift->end_time, $time->timezone);
            $boundary = $end->addSeconds((int) ($end->diffInSeconds($start) / 2));

            if ($time->lt($boundary)) {
                return $time->subDay()->toDateString();
            }
        }

        return $time->toDateString();
    }

    /** @param Collection<int, AttendanceDevicePunch> $punches */
    private function processDay(string $deviceId, Employee $employee, Shift $shift, string $date, Collection $punches): void
    {
        if ($punches->last()->punched_at->gt(now()->addMinutes(5))) {
            throw new DomainException('Device punch is in the future. Verify the device clock and timezone before attendance processing.');
        }

        if ($date < $employee->hire_date->toDateString() || ($employee->termination_date && $date > $employee->termination_date->toDateString())) {
            throw new DomainException('Punch falls outside the matched employee employment dates on '.$date.'.');
        }

        if (config('attendance_device.sync_from') && $date < config('attendance_device.sync_from')) {
            throw new DomainException('Punch precedes the configured synchronization start date; retained for review.');
        }

        $key = hash('sha256', $deviceId.'|'.$employee->id.'|'.$date);
        $segment = AttendanceSegment::where('device_sync_key', $key)->lockForUpdate()->first();

        if (AttendanceSegment::where('employee_id', $employee->id)->whereDate('date', $date)->where(fn ($query) => $query->whereNull('device_sync_key')->orWhere('device_sync_key', '!=', $key))->exists()
            || ($segment && ($segment->correction_reason || $segment->attendance_import_id || ($segment->raw_data['source'] ?? null) !== 'device'))) {
            throw new DomainException('Existing imported or manually corrected attendance takes priority on '.$date.'.');
        }

        if (PayrollRun::where('status', '!=', 'draft')->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)->whereHas('employees', fn ($query) => $query->where('employee_id', $employee->id))->exists()) {
            throw new DomainException('Attendance belongs to a finalized payroll period on '.$date.'.');
        }

        $minutes = 0;
        $periods = 0;
        $entry = null;
        $hasRecordedBreak = false;
        $sequential = config('attendance_device.pairing') === 'sequential';

        if (! in_array(config('attendance_device.pairing'), ['device', 'sequential'], true)) {
            throw new DomainException('Attendance pairing must be device or sequential.');
        }

        foreach ($punches->values() as $index => $punch) {
            $direction = $sequential ? $index % 2 : $punch->direction;
            $isEntry = in_array($direction, [0, 3, 4], true);
            $isExit = in_array($direction, [1, 2, 5], true);

            if ((! $isEntry && ! $isExit) || ($isEntry && $entry) || ($isExit && ! $entry)) {
                throw new DomainException('Ambiguous punch sequence on '.$date.'. Verify device direction codes or select sequential pairing after reviewing a sample.');
            }

            if ($isEntry) {
                $entry = $punch->punched_at;
            } else {
                $minutes += (int) $entry->diffInMinutes($punch->punched_at);
                $periods++;
                $entry = null;
                $hasRecordedBreak = $hasRecordedBreak || $direction === 2;
            }

            if (! $punch->attendance_log_id) {
                $log = AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'punched_at' => $punch->punched_at,
                    'type' => $isEntry ? 'in' : 'out',
                    'source' => 'device',
                ]);
                $punch->update(['attendance_log_id' => $log->id]);
            }
        }

        if ($entry) {
            $this->setError($punches->whereNull('processed_at'), 'Waiting for a matching check-out on '.$date.'.');

            return;
        }

        $first = $punches->first()->punched_at->setTimezone(config('attendance_device.timezone'));
        $last = $punches->last()->punched_at->setTimezone(config('attendance_device.timezone'));
        $start = CarbonImmutable::parse($date.' '.$shift->start_time, config('attendance_device.timezone'));
        $end = CarbonImmutable::parse($date.' '.$shift->end_time, config('attendance_device.timezone'));

        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        $minutes = max(0, $minutes - ($hasRecordedBreak || $periods > 1 ? 0 : (int) $shift->break_minutes));
        $expected = max(1, (int) $shift->expected_minutes);
        $late = $first->gt($start->addMinutes((int) $shift->grace_minutes)) ? max(0, (int) $start->diffInMinutes($first, false)) : 0;
        $early = max(0, (int) $last->diffInMinutes($end, false));
        $holiday = Holiday::whereDate('date', $date)->exists();
        $overtime = $shift->overtime_eligible ? max(0, $minutes - $expected) : 0;
        $segment ??= new AttendanceSegment(['device_sync_key' => $key]);
        $segment->fill([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'date' => $date,
            'fp_no' => $employee->attendance_device_id,
            'schedule_name' => $shift->name,
            'scheduled_start' => $shift->start_time,
            'scheduled_end' => $shift->end_time,
            'clock_in' => $first->format('H:i:s'),
            'clock_out' => $last->format('H:i:s'),
            'late_minutes' => $late,
            'early_minutes' => $early,
            'worked_minutes' => min($minutes, $expected),
            'overtime_minutes' => $overtime,
            'day_fraction' => round($expected / 480, 2),
            'status' => $holiday ? 'Holiday' : ($late > 0 ? 'Late' : ($early > 0 ? 'Early' : 'Present')),
            'exception' => null,
            'raw_data' => ['source' => 'device', 'device_id' => $deviceId, 'punch_ids' => $punches->pluck('id')->all()],
        ]);

        if ($segment->isDirty()) {
            $segment->save();
            $this->summaries->forSegments(collect([$segment]));
            PayrollRun::where('status', 'draft')->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null]);
        }

        AttendanceDevicePunch::whereIn('id', $punches->pluck('id'))->update(['attendance_segment_id' => $segment->id, 'processed_at' => now(), 'processing_error' => null]);
    }

    /** @param Collection<int, AttendanceDevicePunch> $punches */
    private function setError(Collection $punches, ?string $message): void
    {
        AttendanceDevicePunch::whereIn('id', $punches->pluck('id'))->update(['processing_error' => $message]);
    }
}

<?php

namespace App\Services\Hr;

use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceLog;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Support\AttendanceDeviceSettings;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessDeviceAttendance
{
    private array $settings = [];

    public function handle(string $deviceId, ?array $settings = null): void
    {
        $this->settings = $settings ?? AttendanceDeviceSettings::all();
        app(NotifyUnmatchedAttendanceNumbers::class)->handle($deviceId);
        $userIds = AttendanceDevicePunch::where('device_id', $deviceId)->whereNull('processed_at')->distinct()->pluck('device_user_id');

        foreach ($userIds as $userId) {
            $punches = AttendanceDevicePunch::where('device_id', $deviceId)->where('device_user_id', $userId)->orderBy('punched_at')->orderBy('id')->get();
            $employee = Employee::where('attendance_device_id', $userId)->first();

            if (! $employee) {
                $this->setError($punches->whereNull('processed_at'), 'No employee matches device user_id / FP No '.$userId.'.');

                continue;
            }

            if (! $this->settings['processing_enabled']) {
                $this->setError($punches->whereNull('processed_at'), null);

                continue;
            }

            $employee->load(['workAssignments.payPolicy', 'workAssignments.workSchedule.days.shift', 'workAssignments.workSchedule.days.additionalShifts', 'shiftOverrides.shift', 'shiftOverrides.additionalShifts']);

            $groups = collect();
            $openDate = null;
            $openTime = null;
            foreach ($punches as $punch) {
                try {
                    $isEntry = in_array($punch->direction, [0, 3, 4], true);
                    $isExit = in_array($punch->direction, [1, 2, 5], true);
                    $date = $this->settings['pairing'] === 'device' && $isExit && $openDate && $openTime->diffInHours($punch->punched_at) <= 36
                        ? $openDate : app(ResolveEmployeeWork::class)->forPunch($employee, $punch->punched_at, $punch->direction)['date'];
                    if ($isEntry) {
                        $openDate = $date;
                        $openTime = $punch->punched_at;
                    } elseif ($isExit) {
                        $openDate = $openTime = null;
                    }
                    if (! $groups->has($date)) {
                        $groups->put($date, collect());
                    }
                    $groups->get($date)->push($punch);
                } catch (ValidationException $exception) {
                    $this->setError(collect([$punch])->whereNull('processed_at'), $exception->getMessage());
                }
            }
            $groups->each(function (Collection $dayPunches, string $date) use ($deviceId, $employee): void {
                if ($dayPunches->whereNull('processed_at')->isEmpty()) {
                    return;
                }

                try {
                    $shifts = app(ResolveEmployeeWork::class)->forDate($employee, $date)['shifts']->all();
                    DB::transaction(fn () => $this->processDay($deviceId, $employee, $shifts, $date, $dayPunches));
                } catch (DomainException|ValidationException $exception) {
                    $this->setError($dayPunches->whereNull('processed_at'), $exception->getMessage());
                }
            });
        }
    }

    /** @param list<Shift> $shifts
     * @param  Collection<int, AttendanceDevicePunch>  $punches
     */
    private function processDay(string $deviceId, Employee $employee, array $shifts, string $date, Collection $punches): void
    {
        if ($punches->last()->punched_at->gt(now()->addMinutes(5))) {
            throw new DomainException('Device punch is in the future. Verify the device clock and timezone before attendance processing.');
        }

        if ($date < $employee->hire_date->toDateString() || ($employee->termination_date && $date > $employee->termination_date->toDateString())) {
            throw new DomainException('Punch falls outside the matched employee employment dates on '.$date.'.');
        }

        if ($this->settings['sync_from'] && $date < $this->settings['sync_from']) {
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
        $workIntervals = [];
        $entry = null;
        $estimate = null;
        $sequential = $this->settings['pairing'] === 'sequential';

        if (! in_array($this->settings['pairing'], ['device', 'sequential'], true)) {
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
                $workIntervals[] = [$entry->toDateTimeString(), $punch->punched_at->toDateTimeString()];
                $entry = null;
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
            $estimate = $this->estimateCheckout($entry, $shifts, $date);
            if (! $estimate) {
                $this->setError($punches->whereNull('processed_at'), 'Waiting for a matching check-out on '.$date.'.');

                return;
            }
            $minutes += (int) $entry->diffInMinutes($estimate['end']);
            $workIntervals[] = [$entry->toDateTimeString(), $estimate['end']->toDateTimeString()];
        }

        $first = $punches->first()->punched_at->setTimezone($this->settings['timezone']);
        $last = ($estimate['end'] ?? $punches->last()->punched_at)->setTimezone($this->settings['timezone']);
        $shift = $shifts[0] ?? null;
        $windows = collect($shifts)->map(fn (Shift $period): array => app(WorkTimeIntervals::class)->scheduled($period, $date));
        $start = $windows->isNotEmpty() ? $windows->min(fn (array $window) => $window[0]) : $first;
        $end = $windows->isNotEmpty() ? $windows->max(fn (array $window) => $window[1]) : $last;
        $expected = (int) collect($shifts)->sum('expected_minutes');
        $holiday = Holiday::whereDate('date', $date)->exists();
        $segment ??= new AttendanceSegment(['device_sync_key' => $key]);
        $segment->fill([
            'employee_id' => $employee->id,
            'shift_id' => $shift?->id,
            'date' => $date,
            'fp_no' => $employee->attendance_device_id,
            'schedule_name' => collect($shifts)->pluck('name')->join(' + ') ?: 'Rest day',
            'scheduled_start' => $start->format('H:i:s'),
            'scheduled_end' => $end->format('H:i:s'),
            'clock_in' => $first->format('H:i:s'),
            'clock_out' => $last->format('H:i:s'),
            'late_minutes' => 0,
            'early_minutes' => 0,
            'worked_minutes' => min($minutes, $expected),
            'overtime_minutes' => max(0, $minutes - $expected),
            'day_fraction' => round($expected / 480, 2),
            'status' => $holiday ? 'Holiday' : (! $shift ? 'Dayoff' : 'Present'),
            'exception' => $estimate ? 'Estimated check-out' : null,
            'raw_data' => [
                'source' => 'device', 'device_id' => $deviceId, 'punch_ids' => $punches->pluck('id')->all(), 'work_intervals' => $workIntervals,
                ...($estimate ? ['estimated_checkout' => [
                    'punched_at' => $estimate['end']->toDateTimeString(), 'shift_id' => $estimate['shift_id'],
                    'estimated_at' => $segment->raw_data['estimated_checkout']['estimated_at'] ?? now()->toDateTimeString(),
                    'interval' => [$entry->getTimestamp(), $estimate['end']->getTimestamp()],
                ]] : []),
            ],
        ]);

        if ($segment->isDirty()) {
            $segment->save();
            $summary = app(BuildScheduledAttendance::class)->handle($employee, $date, $date)->first();
            if ($summary) {
                $early = $summary->calculation_snapshot['early_minutes'] ?? 0;
                $segment->updateQuietly(['worked_minutes' => $summary->regular_minutes, 'overtime_minutes' => $summary->overtime_minutes, 'late_minutes' => max(0, $summary->late_minutes - $early), 'early_minutes' => $early]);
            }
            app(GeneratePayrollOvertimeEntries::class)->generate($employee, $date, $date);
            PayrollRun::where('status', 'draft')->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null]);
        }

        AttendanceDevicePunch::whereIn('id', $punches->pluck('id'))->update(['attendance_segment_id' => $segment->id, 'processed_at' => now(), 'processing_error' => null]);
    }

    /**
     * @param  list<Shift>  $shifts
     * @return array{end: CarbonImmutable, shift_id: int}|null
     */
    private function estimateCheckout(CarbonImmutable $entry, array $shifts, string $date): ?array
    {
        $candidates = [];
        foreach ($shifts as $shift) {
            [$start, $end] = app(WorkTimeIntervals::class)->scheduled($shift, $date);
            if ($entry->gte($start->subMinutes($shift->capture_before_minutes)) && $entry->lt($end)) {
                $candidates[] = ['start' => $start, 'end' => $end, 'shift' => $shift];
            }
        }
        usort($candidates, fn (array $left, array $right): int => abs($entry->getTimestamp() - $left['start']->getTimestamp()) <=> abs($entry->getTimestamp() - $right['start']->getTimestamp()));
        $candidate = $candidates[0] ?? null;
        if (! $candidate || now()->lt($candidate['end']->addMinutes($candidate['shift']->capture_after_minutes))) {
            return null;
        }

        return ['end' => $candidate['end'], 'shift_id' => $candidate['shift']->id];
    }

    /** @param Collection<int, AttendanceDevicePunch> $punches */
    private function setError(Collection $punches, ?string $message): void
    {
        AttendanceDevicePunch::whereIn('id', $punches->pluck('id'))->update(['processing_error' => $message]);
    }
}

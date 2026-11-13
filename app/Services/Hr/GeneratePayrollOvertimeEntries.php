<?php

namespace App\Services\Hr;

use App\Models\AttendanceDailySummary;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GeneratePayrollOvertimeEntries
{
    public function __construct(private BuildScheduledAttendance $attendance, private ResolveEmployeeWork $work, private CalculateSalaryEntitlement $salary) {}

    public function generate(Employee $employee, string $from, string $to): void
    {
        DB::transaction(function () use ($employee, $from, $to): void {
            $employee = Employee::lockForUpdate()->findOrFail($employee->id);
            $this->generateForEmployee($employee, $from, $to);
        }, 3);
    }

    private function generateForEmployee(Employee $employee, string $from, string $to): void
    {
        $summaries = $this->attendance->handle($employee, $from, $to);
        $holidays = Holiday::whereDate('date', '>=', $from)->whereDate('date', '<=', CarbonImmutable::parse($to)->addDay())->where('counts_as_holiday_overtime', true)->pluck('date')->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())->flip();
        foreach ($summaries as $summary) {
            $snapshot = $summary->calculation_snapshot;
            if (($snapshot['issues'] ?? []) !== []) {
                PayrollOvertimeEntry::where('employee_id', $employee->id)->whereNotNull('pay_policy_id')
                    ->where('source_key', 'like', 'work:'.$summary->date->toDateString().':%')
                    ->where(fn ($query) => $query->whereNull('payroll_run_id')->orWhereHas('payrollRun', fn ($run) => $run->where('status', 'draft')))
                    ->update(['status' => 'rejected', 'minutes' => 0, 'hours' => 0, 'amount' => 0, 'reviewed_by' => null, 'reviewed_at' => null, 'notes' => 'Source attendance needs review. Regenerate after correction.']);

                continue;
            }
            $work = $this->work->forDate($employee, $summary->date->toDateString());
            $policy = $work['policy'];
            $scheduled = $snapshot['scheduled_intervals'] ?? (isset($snapshot['scheduled_interval']) ? [$snapshot['scheduled_interval']] : []);
            $buckets = [];
            foreach ($snapshot['work_intervals'] ?? [] as [$start, $end]) {
                for ($cursor = $start; $cursor < $end;) {
                    $time = CarbonImmutable::createFromTimestamp($cursor, config('app.timezone'));
                    $next = min($end, $time->startOfMinute()->addMinute()->getTimestamp());
                    if ($scheduled) {
                        foreach (array_merge(...$scheduled) as $boundary) {
                            if ($boundary > $cursor) {
                                $next = min($next, $boundary);
                            }
                        }
                    }
                    $date = $time->toDateString();
                    $holiday = $holidays->has($date);
                    $category = null;
                    if ($holiday) {
                        $category = 'holiday';
                    } elseif (! $work['shift']) {
                        $category = 'rest';
                    } elseif (! collect($scheduled)->contains(fn (array $window): bool => $cursor >= $window[0] && $cursor < $window[1])) {
                        $clock = $time->format('H:i:s');
                        $night = $policy->night_start > $policy->night_end
                            ? $clock >= $policy->night_start || $clock < $policy->night_end
                            : $clock >= $policy->night_start && $clock < $policy->night_end;
                        $category = $night ? 'night' : 'day';
                    }
                    if ($category) {
                        $bucketKey = $date.'|'.$category;
                        $buckets[$bucketKey] ??= ['date' => $date, 'category' => $category, 'seconds' => 0, 'intervals' => []];
                        $buckets[$bucketKey]['seconds'] += $next - $cursor;
                        $buckets[$bucketKey]['intervals'][] = [$cursor, $next];
                    }
                    $cursor = $next;
                }
            }
            $generated = [];
            DB::transaction(function () use ($buckets, $policy, $employee, $summary, &$generated): void {
                foreach ($buckets as $bucket) {
                    $minutes = (int) floor($bucket['seconds'] / 60 / $policy->overtime_increment_minutes) * (int) $policy->overtime_increment_minutes;
                    if ($minutes <= 0) {
                        continue;
                    }
                    $key = 'work:'.$summary->date->toDateString().':'.$bucket['date'].':'.$bucket['category'];
                    $generated[] = $key;
                    $rate = round($policy->hourlyRate($this->salary->salaryOn($employee, $bucket['date'])), 4);
                    $multiplier = $policy->multiplier($bucket['category']);
                    $entry = PayrollOvertimeEntry::with('payrollRun')->where('employee_id', $employee->id)->where('source_key', $key)->lockForUpdate()->first()
                        ?? new PayrollOvertimeEntry(['employee_id' => $employee->id, 'source_key' => $key, 'status' => 'pending']);
                    if ($entry->payroll_run_id && $entry->payrollRun?->status !== 'draft') {
                        continue;
                    }
                    $values = ['date' => $bucket['date'], 'pay_policy_id' => $policy->id, 'category' => $bucket['category'], 'minutes' => $minutes, 'hours' => round($minutes / 60, 2), 'hourly_rate' => $rate, 'multiplier' => $multiplier, 'amount' => round($minutes / 60 * $rate * $multiplier, 2)];
                    $entry->fill($values);
                    $sourceSignature = self::sourceSignature($summary);
                    $changed = $entry->exists && ($entry->isDirty(array_keys($values)) || ($entry->calculation_snapshot['source_signature'] ?? null) !== $sourceSignature);
                    $entry->fill([...$values, 'started_at' => CarbonImmutable::createFromTimestamp($bucket['intervals'][0][0], config('app.timezone')), 'ended_at' => CarbonImmutable::createFromTimestamp($bucket['intervals'][array_key_last($bucket['intervals'])][1], config('app.timezone')), 'source_type' => AttendanceDailySummary::class, 'source_id' => $summary->id, 'calculation_snapshot' => ['version' => 2, 'work_date' => $summary->date->toDateString(), 'policy' => $policy->attributesToArray(), 'intervals' => app(WorkTimeIntervals::class)->merge($bucket['intervals']), 'daily_limit_exceeded' => $minutes > $policy->maximum_daily_overtime_minutes]]);
                    if ($changed) {
                        $entry->fill(['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
                    }
                    $entry->calculation_snapshot = [...$entry->calculation_snapshot, 'source_signature' => $sourceSignature];
                    $entry->save();
                }
                PayrollOvertimeEntry::where('employee_id', $employee->id)->whereNotNull('pay_policy_id')->where('source_key', 'like', 'work:'.$summary->date->toDateString().':%')->whereNotIn('source_key', $generated)
                    ->where(fn ($query) => $query->whereNull('payroll_run_id')->orWhereHas('payrollRun', fn ($run) => $run->where('status', 'draft')))
                    ->update(['status' => 'rejected', 'minutes' => 0, 'hours' => 0, 'amount' => 0, 'notes' => 'Source attendance no longer contains this overtime.']);
            });
        }
        $this->refreshLimitWarnings($employee, $from, $to);
    }

    public static function sourceSignature(AttendanceDailySummary $summary): string
    {
        $snapshot = collect($summary->calculation_snapshot)->only(['work_intervals', 'scheduled_interval', 'scheduled_intervals', 'shift', 'shifts', 'policy', 'issues'])->all();
        $snapshot['policy'] = collect($snapshot['policy'] ?? [])->except(['is_active', 'updated_at'])->all();

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function refreshLimitWarnings(Employee $employee, string $from, string $to): void
    {
        $entries = PayrollOvertimeEntry::with(['payPolicy', 'payrollRun'])->where('employee_id', $employee->id)->whereNotNull('pay_policy_id')
            ->whereDate('date', '>=', CarbonImmutable::parse($from)->startOfWeek())->whereDate('date', '<=', CarbonImmutable::parse($to)->endOfWeek())->get();
        foreach ($entries as $entry) {
            if ($entry->payroll_run_id && $entry->payrollRun?->status !== 'draft') {
                continue;
            }
            $dayMinutes = $entries->filter(fn ($candidate): bool => $candidate->date->isSameDay($entry->date) && $candidate->status !== 'rejected')->sum('minutes');
            $weekStart = $entry->date->copy()->startOfWeek();
            $weekMinutes = $entries->filter(fn ($candidate): bool => $candidate->date->betweenIncluded($weekStart, $weekStart->copy()->endOfWeek()) && $candidate->status !== 'rejected')->sum('minutes');
            $entry->update(['calculation_snapshot' => [...($entry->calculation_snapshot ?? []), 'daily_limit_exceeded' => $dayMinutes > $entry->payPolicy->maximum_daily_overtime_minutes, 'weekly_limit_exceeded' => $weekMinutes > $entry->payPolicy->maximum_weekly_overtime_minutes]]);
        }
    }

    /** @return array{minutes: int, hours: float, amount: float, ids: list<int>} */
    public function approvedTotals(PayrollRun $run, Employee|PayrollRunEmployee $employee): array
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee->employee_id;
        $entries = PayrollOvertimeEntry::where('employee_id', $employeeId)->whereNotNull('pay_policy_id')->where('status', 'approved')
            ->whereDate('date', '>=', $run->period_start)->whereDate('date', '<=', $run->period_end)
            ->where(fn ($query) => $query->whereNull('payroll_run_id')->orWhere('payroll_run_id', $run->id)->orWhereHas('payrollRun', fn ($draft) => $draft->where('status', 'draft')))->get();

        return ['minutes' => (int) $entries->sum('minutes'), 'hours' => round($entries->sum('minutes') / 60, 2), 'amount' => round($entries->sum('amount'), 2), 'ids' => $entries->modelKeys()];
    }

    public function linkEntriesToPayrollRow(PayrollRunEmployee $row): void
    {
        $ids = $row->calculation_snapshot['approved_overtime_entry_ids'] ?? [];
        PayrollOvertimeEntry::whereIn('id', $ids)->where('employee_id', $row->employee_id)->update(['payroll_run_id' => $row->payroll_run_id, 'payroll_run_employee_id' => $row->id]);
    }
}

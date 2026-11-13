<?php

namespace App\Services\Hr;

use App\Models\AttendanceSegment;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WorkTimeIntervals
{
    /** @return array{CarbonImmutable, CarbonImmutable} */
    public function scheduled(Shift $shift, string $date): array
    {
        $start = CarbonImmutable::parse($date.' '.$shift->start_time, config('app.timezone'));
        $end = CarbonImmutable::parse($date.' '.$shift->end_time, config('app.timezone'));

        return [$start, $end->lte($start) ? $end->addDay() : $end];
    }

    /** @return list<array{int, int}> */
    public function paidSchedule(Shift $shift, string $date): array
    {
        [$start, $end] = $this->scheduled($shift, $date);
        $intervals = [[$start->getTimestamp(), $end->getTimestamp()]];
        $break = $this->unpaidBreak($shift, $date);

        return $break ? $this->subtract($intervals, $break) : $intervals;
    }

    /** @return array{int, int}|null */
    private function unpaidBreak(Shift $shift, string $date): ?array
    {
        if ($shift->break_paid || ! $shift->break_start || ! $shift->break_end) {
            return null;
        }
        [$scheduledStart] = $this->scheduled($shift, $date);
        $start = CarbonImmutable::parse($date.' '.$shift->break_start, config('app.timezone'));
        if ($start->lt($scheduledStart)) {
            $start = $start->addDay();
        }
        $end = CarbonImmutable::parse($start->toDateString().' '.$shift->break_end, config('app.timezone'));
        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return [$start->getTimestamp(), $end->getTimestamp()];
    }

    /** @param Collection<int, AttendanceSegment> $segments
     * @param  Shift|list<Shift>|null  $shift
     * @return list<array{int, int}>
     */
    public function actual(Collection $segments, Shift|array|null $shift, string $date): array
    {
        $intervals = [];
        foreach ($segments as $segment) {
            if ($segment->status === 'Absent') {
                continue;
            }
            $recorded = $segment->raw_data['work_intervals'] ?? null;
            if (is_array($recorded)) {
                foreach ($recorded as $interval) {
                    $intervals[] = [CarbonImmutable::parse($interval[0])->getTimestamp(), CarbonImmutable::parse($interval[1])->getTimestamp()];
                }

                continue;
            }
            if (! $segment->clock_in || ! $segment->clock_out) {
                continue;
            }
            $start = CarbonImmutable::parse($date.' '.$segment->clock_in, config('app.timezone'));
            $end = CarbonImmutable::parse($date.' '.$segment->clock_out, config('app.timezone'));
            if ($end->lte($start)) {
                $end = $end->addDay();
            }
            $intervals[] = [$start->getTimestamp(), $end->getTimestamp()];
        }
        $intervals = $this->merge($intervals);
        foreach (is_array($shift) ? $shift : ($shift ? [$shift] : []) as $periodShift) {
            if ($break = $this->unpaidBreak($periodShift, $date)) {
                $intervals = $this->subtract($intervals, $break);
            }
        }

        return array_values(array_filter($intervals, fn (array $interval): bool => $interval[1] > $interval[0]));
    }

    /** @param list<array{int, int}> $intervals
     * @return list<array{int, int}>
     */
    public function merge(array $intervals): array
    {
        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $result = [];
        foreach ($intervals as [$start, $end]) {
            $last = count($result) - 1;
            if ($last >= 0 && $start <= $result[$last][1]) {
                $result[$last][1] = max($end, $result[$last][1]);
            } else {
                $result[] = [$start, $end];
            }
        }

        return $result;
    }

    /** @param list<array{int, int}> $left
     * @param  list<array{int, int}>  $right
     * @return list<array{int, int}>
     */
    public function intersect(array $left, array $right): array
    {
        $result = [];
        foreach ($left as [$leftStart, $leftEnd]) {
            foreach ($right as [$rightStart, $rightEnd]) {
                $start = max($leftStart, $rightStart);
                $end = min($leftEnd, $rightEnd);
                if ($end > $start) {
                    $result[] = [$start, $end];
                }
            }
        }

        return $this->merge($result);
    }

    /** @param list<array{int, int}> $intervals
     * @param  array{int, int}  $excluded
     * @return list<array{int, int}>
     */
    public function subtract(array $intervals, array $excluded): array
    {
        $result = [];
        foreach ($intervals as [$start, $end]) {
            if ($end <= $excluded[0] || $start >= $excluded[1]) {
                $result[] = [$start, $end];

                continue;
            }
            if ($start < $excluded[0]) {
                $result[] = [$start, $excluded[0]];
            }
            if ($end > $excluded[1]) {
                $result[] = [$excluded[1], $end];
            }
        }

        return $result;
    }
}

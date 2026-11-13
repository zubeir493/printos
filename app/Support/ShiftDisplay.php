<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

class ShiftDisplay
{
    public static function duration(int $minutes): string
    {
        return intdiv($minutes, 60).'h'.($minutes % 60 ? ' '.($minutes % 60).'m' : '');
    }

    public static function time(?string $time): string
    {
        return filled($time) ? CarbonImmutable::parse($time)->format('g:i A') : '';
    }

    /** @param array<string, mixed> $data
     * @return array{valid: bool, message: string, paid: string, span: string, break: string, overnight: bool}
     */
    public static function preview(array $data): array
    {
        $empty = ['valid' => false, 'message' => 'Choose start and end times to preview the shift.', 'paid' => '', 'span' => '', 'break' => '', 'overnight' => false];
        if (blank($data['start_time'] ?? null) || blank($data['end_time'] ?? null)) {
            return $empty;
        }
        try {
            foreach (['start_time', 'end_time', 'break_start', 'break_end'] as $field) {
                if (filled($data[$field] ?? null)) {
                    $data[$field] = CarbonImmutable::parse($data[$field])->format('H:i:s');
                }
            }
            $start = CarbonImmutable::parse('2000-01-01 '.$data['start_time']);
            $end = CarbonImmutable::parse('2000-01-01 '.$data['end_time']);
            if ($start->eq($end)) {
                return [...$empty, 'message' => 'Start and end times must be different.'];
            }
            $overnight = $end->lt($start);
            $end = $overnight ? $end->addDay() : $end;
            $span = (int) $start->diffInMinutes($end);
            $break = 0;
            if ($data['_has_break'] ?? (filled($data['break_start'] ?? null) || filled($data['break_end'] ?? null))) {
                if (blank($data['break_start'] ?? null) || blank($data['break_end'] ?? null)) {
                    return [...$empty, 'message' => 'Choose both break times to calculate paid hours.'];
                }
                $breakStart = CarbonImmutable::parse('2000-01-01 '.$data['break_start']);
                $breakStart = $breakStart->lt($start) ? $breakStart->addDay() : $breakStart;
                $breakEnd = CarbonImmutable::parse($breakStart->toDateString().' '.$data['break_end']);
                $breakEnd = $breakEnd->lte($breakStart) ? $breakEnd->addDay() : $breakEnd;
                if ($breakStart->lt($start) || $breakEnd->gt($end)) {
                    return [...$empty, 'message' => 'The break must fall within the shift hours.'];
                }
                $break = (int) $breakStart->diffInMinutes($breakEnd);
            }
            $paid = $span - (($data['break_paid'] ?? false) ? 0 : $break);
            if ($paid <= 0) {
                return [...$empty, 'message' => 'The shift must include some paid working time.'];
            }

            return ['valid' => true, 'message' => self::time($data['start_time']).' to '.self::time($data['end_time']).($overnight ? ' the following day' : ''), 'paid' => self::duration($paid), 'span' => self::duration($span), 'break' => $break ? self::duration($break).(($data['break_paid'] ?? false) ? ' paid' : ' unpaid') : 'No break', 'overnight' => $overnight];
        } catch (Throwable) {
            return [...$empty, 'message' => 'Enter valid shift and break times.'];
        }
    }
}

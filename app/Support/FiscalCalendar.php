<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class FiscalCalendar
{
    /**
     * @return array<string, string>
     */
    public static function calendarOptions(): array
    {
        return [
            'gregorian' => 'Gregorian fiscal year (January 1)',
            'ethiopian' => 'Ethiopian fiscal year (Hamle 1)',
        ];
    }

    public static function currentFiscalYearStart(?CarbonImmutable $date = null): CarbonImmutable
    {
        $date ??= CarbonImmutable::now();

        return self::fiscalYearStartFor($date);
    }

    public static function fiscalYearStartFor(CarbonImmutable $date): CarbonImmutable
    {
        if (self::calendarSystem() === 'ethiopian') {
            $start = self::ethiopianFiscalYearStart($date->year);

            return $date->lt($start) ? self::ethiopianFiscalYearStart($date->year - 1) : $start;
        }

        return $date->startOfYear();
    }

    /**
     * @return array<string, string>
     */
    public static function payrollMonthOptions(?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::now();
        $start = self::currentFiscalYearStart($date);
        $months = self::calendarSystem() === 'ethiopian'
            ? ['Hamle', 'Nehase', 'Pagume', 'Meskerem', 'Tikimt', 'Hidar', 'Tahsas', 'Tir', 'Yekatit', 'Megabit', 'Miazia', 'Ginbot', 'Sene']
            : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        $options = [];

        foreach ($months as $index => $month) {
            $monthStart = $start->addMonthsNoOverflow($index);
            $year = self::calendarSystem() === 'ethiopian'
                ? self::ethiopianYearFor($monthStart)
                : $monthStart->year;

            $options[$monthStart->toDateString()] = $month.' '.$year;
        }

        return $options;
    }

    public static function payrollMonthLabel(Carbon|string|null $date): ?string
    {
        if (! $date) {
            return null;
        }

        $date = CarbonImmutable::parse($date);
        $start = self::fiscalYearStartFor($date);
        $monthIndex = max(0, min(12, $start->diffInMonths($date)));

        return self::payrollMonthOptions($date)[$start->addMonthsNoOverflow($monthIndex)->toDateString()] ?? $date->format('F Y');
    }

    public static function formatDateRange(Carbon|string|null $start, Carbon|string|null $end): string
    {
        if (! $start || ! $end) {
            return '-';
        }

        $start = CarbonImmutable::parse($start);
        $end = CarbonImmutable::parse($end);

        if ($start->year === $end->year) {
            return $start->format('M j').' - '.$end->format('M j, Y');
        }

        return $start->format('M j, Y').' - '.$end->format('M j, Y');
    }

    public static function payrollPeriodLabel(string $periodType, Carbon|string|null $payrollMonth, Carbon|string|null $periodStart, Carbon|string|null $periodEnd): string
    {
        if ($periodType === 'monthly' && $payrollMonth) {
            return self::payrollMonthLabel($payrollMonth) ?? self::formatDateRange($periodStart, $periodEnd);
        }

        return self::formatDateRange($periodStart, $periodEnd);
    }

    public static function currentPayrollMonthStart(?CarbonImmutable $date = null): CarbonImmutable
    {
        $date ??= CarbonImmutable::now();
        $start = self::fiscalYearStartFor($date);
        $monthIndex = max(0, min(12, $start->diffInMonths($date)));

        return $start->addMonthsNoOverflow($monthIndex);
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, pay_date: CarbonImmutable}
     */
    public static function payrollPeriodForMonth(Carbon|string $date): array
    {
        $start = CarbonImmutable::parse($date)->startOfDay();
        $end = $start->addMonthNoOverflow()->subDay()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'pay_date' => $end,
        ];
    }

    private static function calendarSystem(): string
    {
        return Setting::getSettings()->fiscal_calendar ?: 'gregorian';
    }

    private static function ethiopianFiscalYearStart(int $gregorianYear): CarbonImmutable
    {
        return CarbonImmutable::create($gregorianYear, 7, 8)->startOfDay();
    }

    private static function ethiopianYearFor(CarbonImmutable $date): int
    {
        $newYear = CarbonImmutable::create($date->year, 9, 11)->startOfDay();

        return $date->lt($newYear) ? $date->year - 8 : $date->year - 7;
    }
}

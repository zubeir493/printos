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
            $options[$monthStart->toDateString()] = $month.' '.$monthStart->format('Y');
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

    private static function calendarSystem(): string
    {
        return Setting::getSettings()->fiscal_calendar ?: 'gregorian';
    }

    private static function ethiopianFiscalYearStart(int $gregorianYear): CarbonImmutable
    {
        return CarbonImmutable::create($gregorianYear, 7, 8)->startOfDay();
    }
}

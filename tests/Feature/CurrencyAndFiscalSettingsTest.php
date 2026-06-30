<?php

use App\Models\Setting;
use App\Support\FiscalCalendar;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('money values use the selected currency suffix', function () {
    Setting::create([
        'currency_code' => 'USD',
        'currency_symbol' => 'USD',
        'fiscal_calendar' => 'gregorian',
    ]);

    expect(Money::format(1000, precision: 2))->toBe('1,000.00 USD')
        ->and(Money::currencyOptions())->toHaveKeys(['ETB', 'USD', 'MYR', 'SAR', 'AED', 'KSH']);
});

test('gregorian fiscal year starts on january first', function () {
    Setting::create([
        'currency_code' => 'ETB',
        'currency_symbol' => 'Birr',
        'fiscal_calendar' => 'gregorian',
    ]);

    expect(FiscalCalendar::fiscalYearStartFor(CarbonImmutable::parse('2026-06-29'))->toDateString())
        ->toBe('2026-01-01');
});

test('ethiopian fiscal year starts on hamle one', function () {
    Setting::create([
        'currency_code' => 'ETB',
        'currency_symbol' => 'Birr',
        'fiscal_calendar' => 'ethiopian',
    ]);

    expect(FiscalCalendar::fiscalYearStartFor(CarbonImmutable::parse('2026-08-01'))->toDateString())
        ->toBe('2026-07-08')
        ->and(FiscalCalendar::fiscalYearStartFor(CarbonImmutable::parse('2026-06-30'))->toDateString())
        ->toBe('2025-07-08');
});

test('ethiopian payroll month options start with hamle', function () {
    Setting::create([
        'currency_code' => 'ETB',
        'currency_symbol' => 'Birr',
        'fiscal_calendar' => 'ethiopian',
    ]);

    $options = FiscalCalendar::payrollMonthOptions(CarbonImmutable::parse('2026-08-01'));

    expect(array_key_first($options))->toBe('2026-07-08')
        ->and(reset($options))->toBe('Hamle 2026')
        ->and(FiscalCalendar::payrollMonthLabel('2026-07-08'))->toBe('Hamle 2026');
});

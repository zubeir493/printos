<?php

use App\Support\Money;

test('money values are formatted with thousands separators and birr', function () {
    expect(Money::format(1000))->toBe('1,000 Birr')
        ->and(Money::format(1250000.75))->toBe('1,250,001 Birr')
        ->and(Money::format(1250000.75, precision: 2))->toBe('1,250,000.75 Birr');
});

test('money values can be abbreviated for widgets', function () {
    expect(Money::abbreviate(1250000, precision: 2))->toBe('1.25M Birr')
        ->and(Money::abbreviate(1000, precision: 0))->toBe('1K Birr');
});

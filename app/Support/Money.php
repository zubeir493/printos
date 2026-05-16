<?php

namespace App\Support;

use Illuminate\Support\Number;

class Money
{
    public static function format(float|int|string|null $amount, int $precision = 0): string
    {
        return number_format((float) ($amount ?? 0), $precision).' Birr';
    }

    public static function abbreviate(float|int|string|null $amount, int $precision): string
    {
        return Number::abbreviate((float) ($amount ?? 0), precision: $precision).' Birr';
    }
}

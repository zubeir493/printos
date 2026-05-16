<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DateTimeDisplay
{
    public static function dateOrDateTime(CarbonInterface|string|null $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);

        if ($date->isStartOfDay()) {
            return $date->format('d M Y');
        }

        return $date->format('d M Y, h:i A');
    }
}

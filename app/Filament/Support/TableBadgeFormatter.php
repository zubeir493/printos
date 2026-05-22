<?php

namespace App\Filament\Support;

use BackedEnum;
use Illuminate\Support\Str;

class TableBadgeFormatter
{
    public static function format(mixed $state): mixed
    {
        if ($state instanceof BackedEnum) {
            $state = $state->value;
        }

        if (! is_string($state)) {
            return $state;
        }

        return Str::of($state)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }
}

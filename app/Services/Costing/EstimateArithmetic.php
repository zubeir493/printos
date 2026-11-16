<?php

namespace App\Services\Costing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class EstimateArithmetic
{
    public static function amount(float|int|string $quantity, float|int|string $rate): float
    {
        return BigDecimal::of((string) $quantity)->multipliedBy((string) $rate)
            ->toScale(2, RoundingMode::HALF_UP)->toFloat();
    }

    /** @param array<int, float|int|string> $amounts */
    public static function sum(array $amounts): float
    {
        $total = BigDecimal::zero();
        foreach ($amounts as $amount) {
            $total = $total->plus((string) $amount);
        }

        return $total->toScale(2, RoundingMode::HALF_UP)->toFloat();
    }

    public static function sellingPrice(float $cost, float $percent, string $mode): float
    {
        $percentage = BigDecimal::of((string) $percent)->dividedBy(100, 10, RoundingMode::HALF_UP);
        $cost = BigDecimal::of((string) $cost);

        return ($mode === 'markup'
            ? $cost->multipliedBy($percentage->plus(1))
            : $cost->dividedBy(BigDecimal::one()->minus($percentage), 10, RoundingMode::HALF_UP))
            ->toScale(2, RoundingMode::HALF_UP)->toFloat();
    }
}

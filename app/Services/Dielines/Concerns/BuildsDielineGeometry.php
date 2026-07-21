<?php

namespace App\Services\Dielines\Concerns;

trait BuildsDielineGeometry
{
    /**
     * @param  array<string, mixed>  $dimensions
     */
    protected function dimension(array $dimensions, string $key, float $default): float
    {
        return max(0, (float) ($dimensions[$key] ?? $default));
    }

    /**
     * @return array{x1: float, y1: float, x2: float, y2: float}
     */
    protected function line(float $x1, float $y1, float $x2, float $y2): array
    {
        return compact('x1', 'y1', 'x2', 'y2');
    }

    /**
     * @return array<int, array{x: float, y: float}>
     */
    protected function polygon(float $x, float $y, float $width, float $height): array
    {
        return [
            ['x' => $x, 'y' => $y],
            ['x' => $x + $width, 'y' => $y],
            ['x' => $x + $width, 'y' => $y + $height],
            ['x' => $x, 'y' => $y + $height],
        ];
    }

    /**
     * @return array{x: float, y: float, text: string}
     */
    protected function label(float $x, float $y, string $text): array
    {
        return compact('x', 'y', 'text');
    }
}

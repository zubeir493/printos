<?php

namespace App\Services\Dielines\Concerns;

trait BuildsPrecisionGeometry
{
    use BuildsPackagingPanels;

    /** @return array<int, array{x1: float, y1: float, x2: float, y2: float}> */
    protected function precisionSlot(float $x, float $y, float $width, float $height): array
    {
        $radius = $height / 2;

        return [
            $this->line($x + $radius, $y, $x + $width - $radius, $y),
            ...$this->precisionArc($x + $width - $radius, $y + $radius, $radius, -M_PI / 2, M_PI / 2),
            $this->line($x + $width - $radius, $y + $height, $x + $radius, $y + $height),
            ...$this->precisionArc($x + $radius, $y + $radius, $radius, M_PI / 2, 3 * M_PI / 2),
        ];
    }

    /**
     * @param  array<int, array{0: float|int, 1: float|int}>  $points
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function precisionPolyline(array $points, bool $closed = false): array
    {
        if ($closed) {
            $points[] = $points[0];
        }

        $lines = [];

        foreach (array_slice($points, 1) as $index => $point) {
            [$x, $y] = $points[$index];

            if (hypot($point[0] - $x, $point[1] - $y) > 0.000001) {
                $lines[] = $this->line($x, $y, $point[0], $point[1]);
            }
        }

        return $lines;
    }

    /**
     * Tessellate a circular arc with a maximum chord error of 0.01 mm.
     *
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function precisionArc(float $cx, float $cy, float $radius, float $start, float $end): array
    {
        if ($radius <= 0) {
            return [];
        }

        $sweep = $end - $start;
        $step = 2 * acos(max(-1, min(1, 1 - 0.01 / $radius)));
        $segments = max(4, 4 * (int) ceil(abs($sweep) / max($step, 0.001) / 4));
        $points = [];

        for ($index = 0; $index <= $segments; $index++) {
            $angle = $start + $sweep * $index / $segments;
            $points[] = [$cx + $radius * cos($angle), $cy + $radius * sin($angle)];
        }

        return $this->precisionPolyline($points);
    }

    /** @return array<int, array{x1: float, y1: float, x2: float, y2: float}> */
    protected function bowedLine(float $left, float $right, float $y, float $sagitta): array
    {
        $half = ($right - $left) / 2;

        if (abs($sagitta) < 0.000001 || $half <= 0) {
            return [$this->line($left, $y, $right, $y)];
        }

        $depth = abs($sagitta);
        $radius = ($half * $half + $depth * $depth) / (2 * $depth);
        $sign = $sagitta > 0 ? 1 : -1;
        $cx = ($left + $right) / 2;
        $cy = $y - $sign * ($radius - $depth);
        $start = atan2($y - $cy, -$half);
        $end = atan2($y - $cy, $half);

        return $this->precisionArc($cx, $cy, $radius, $start, $end);
    }

    /**
     * @param  array<int, array{x1: float, y1: float, x2: float, y2: float}>  $lines
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function reflectLines(array $lines, float $axis = 0): array
    {
        return array_map(fn (array $line): array => $this->line(2 * $axis - $line['x1'], $line['y1'], 2 * $axis - $line['x2'], $line['y2']), $lines);
    }
}

<?php

namespace App\Services\Dielines\Geometry;

use Closure;

class PanelProfile
{
    /**
     * Project tooling details between independently positioned panel stations.
     *
     * @param  Closure(float, float): array{float, float}  $project
     * @param  array<int, float>  $stations
     * @param  array<int, float>  $xStations
     * @return array<string, array<int, array{x1: float, y1: float, x2: float, y2: float}>>
     */
    public function project(string $name, Closure $project, array $stations = [], array $xStations = []): array
    {
        $profile = json_decode(file_get_contents(__DIR__.'/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
        $layers = [];

        foreach ($profile as $layer => $primitives) {
            $layers[$layer] = [];

            foreach ($primitives as $primitive) {
                $points = count($primitive) === 4
                    ? [array_slice($primitive, 0, 2), array_slice($primitive, 2, 2)]
                    : $this->arc($primitive);

                foreach (array_slice($points, 1) as $index => $end) {
                    $start = $points[$index];
                    $fractions = [0, 1];

                    if (abs($end[1] - $start[1]) > 0.000001) {
                        foreach ($stations as $station) {
                            $fraction = ($station - $start[1]) / ($end[1] - $start[1]);

                            if ($fraction > 0.000001 && $fraction < 0.999999) {
                                $fractions[] = $fraction;
                            }
                        }
                    }

                    if (abs($end[0] - $start[0]) > 0.000001) {
                        foreach ($xStations as $station) {
                            $fraction = ($station - $start[0]) / ($end[0] - $start[0]);

                            if ($fraction > 0.000001 && $fraction < 0.999999) {
                                $fractions[] = $fraction;
                            }
                        }
                    }

                    $fractions = array_unique($fractions);
                    sort($fractions);
                    $previous = null;

                    foreach ($fractions as $fraction) {
                        [$x, $y] = $project($start[0] + ($end[0] - $start[0]) * $fraction, $start[1] + ($end[1] - $start[1]) * $fraction);

                        if ($previous !== null && hypot($x - $previous[0], $y - $previous[1]) > 0.000001) {
                            $layers[$layer][] = ['x1' => $previous[0], 'y1' => $previous[1], 'x2' => $x, 'y2' => $y];
                        }

                        $previous = [$x, $y];
                    }
                }
            }
        }

        return $layers;
    }

    /**
     * @param  array<int, float>  $primitive
     * @return array<int, array{float, float}>
     */
    private function arc(array $primitive): array
    {
        [$cx, $cy, $radius, $x1, $y1, $x2, $y2, $clockwise] = $primitive;
        $start = atan2($y1 - $cy, $x1 - $cx);
        $end = atan2($y2 - $cy, $x2 - $cx);

        if (! $clockwise && $end >= $start) {
            $end -= 2 * M_PI;
        } elseif ($clockwise && $end <= $start) {
            $end += 2 * M_PI;
        }

        $segments = max(2, (int) ceil(abs($end - $start) / (2 * acos(max(-1, 1 - 0.002 / $radius)))));
        $points = [[$x1, $y1]];

        for ($index = 1; $index < $segments; $index++) {
            $angle = $start + ($end - $start) * $index / $segments;
            $points[] = [$cx + $radius * cos($angle), $cy + $radius * sin($angle)];
        }

        $points[] = [$x2, $y2];

        return $points;
    }

    /** @param array<int, array{float, float}> $stations */
    public static function station(float $value, array $stations): float
    {
        for ($index = 1; $index < count($stations); $index++) {
            if ($value <= $stations[$index][0] || $index === count($stations) - 1) {
                [$from, $to] = [$stations[$index - 1], $stations[$index]];

                return $from[1] + ($value - $from[0]) * ($to[1] - $from[1]) / ($to[0] - $from[0]);
            }
        }

        return $value;
    }
}

<?php

namespace App\Services\Dielines\Concerns;

use App\Services\Dielines\Geometry\PanelProfile;

trait BuildsReferencedDielines
{
    use BuildsPrecisionGeometry;

    /**
     * @param  array<int, array{float, float}>  $columns
     * @param  array<int, array{float, float}>  $rows
     * @param  array<int, array{x: float, y: float, text: string}>  $labels
     * @param  array<int, array{x1: float, y1: float, x2: float, y2: float}>  $extraCuts
     * @return array<string, mixed>
     */
    protected function referencedGeometry(string $profile, array $columns, array $rows, array $labels = [], array $extraCuts = []): array
    {
        $layers = (new PanelProfile)->project(
            $profile,
            fn (float $x, float $y): array => [PanelProfile::station($x, $columns), PanelProfile::station($y, $rows)],
            array_column($rows, 0),
            array_column($columns, 0),
        );
        $layers['cut'] = [...$layers['cut'], ...$extraCuts];

        return $this->panelGeometry([$layers], $labels);
    }
}

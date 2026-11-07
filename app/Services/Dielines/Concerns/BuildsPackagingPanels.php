<?php

namespace App\Services\Dielines\Concerns;

use App\Services\Dielines\Geometry\DielinePath;

trait BuildsPackagingPanels
{
    use BuildsDielineGeometry;

    /**
     * The first and last points are the hinge ends. Only the free edges are cut.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>  $profile
     * @return array{cut: array, crease: array, glue: array}
     */
    protected function profilePanel(float $x, float $y, float $span, string $direction, array $profile): array
    {
        return [
            'cut' => $this->drawPath($x, $y, $direction, function (DielinePath $path) use ($profile): void {
                $path->moveTo(...$profile[0]);

                foreach (array_slice($profile, 1) as $point) {
                    $path->lineTo(...$point);
                }
            }),
            'crease' => $this->drawPath($x, $y, $direction, fn (DielinePath $path): DielinePath => $path->moveTo(0, 0)->lineTo($span, 0)),
            'glue' => [],
        ];
    }

    /** @return array{cut: array, crease: array, glue: array} */
    protected function plainPanel(float $x, float $y, float $span, float $depth, string $direction, float $chamfer = 0): array
    {
        $chamfer = min($chamfer, $span / 2, $depth);

        return $this->profilePanel($x, $y, $span, $direction, [
            [0, 0], [0, -$depth + $chamfer], [$chamfer, -$depth],
            [$span - $chamfer, -$depth], [$span, -$depth + $chamfer], [$span, 0],
        ]);
    }

    /** @return array{cut: array, crease: array, glue: array} */
    protected function relievedFlap(float $x, float $y, float $span, float $depth, string $direction): array
    {
        $inset = min(3, $span / 8, $depth / 4);

        return $this->profilePanel($x, $y, $span, $direction, [[0, 0], [$inset, -$depth], [$span - $inset, -$depth], [$span, 0]]);
    }

    /** @return array{cut: array, crease: array, glue: array} */
    protected function tabbedEndWall(float $x, float $y, float $span, float $depth, float $tab, string $direction, bool $glued = true): array
    {
        $bevel = min($depth / 4, $tab / 3);
        $wall = $this->profilePanel($x, $y, $span, $direction, [
            [0, 0], [-$tab, -$bevel], [-$tab, -$depth + $bevel], [0, -$depth],
            [$span, -$depth], [$span + $tab, -$depth + $bevel], [$span + $tab, -$bevel], [$span, 0],
        ]);
        $wall['crease'] = [...$wall['crease'], ...$this->drawPath($x, $y, $direction, function (DielinePath $path) use ($span, $depth): void {
            $path->moveTo(0, 0)->lineTo(0, -$depth);
            $path->moveTo($span, 0)->lineTo($span, -$depth);
        })];

        if ($glued && $tab > 0 && $depth > 0) {
            $sign = $direction === 'top' ? -1 : 1;

            foreach ([[-$tab, 0], [$span, $span + $tab]] as [$left, $right]) {
                $wall['glue'][] = ['points' => [
                    ['x' => $x + $left, 'y' => $y + $sign * $bevel],
                    ['x' => $x + $left, 'y' => $y + $sign * ($depth - $bevel)],
                    ['x' => $x + $right, 'y' => $y + $sign * ($depth - $bevel)],
                    ['x' => $x + $right, 'y' => $y + $sign * $bevel],
                ]];
            }
        }

        return $wall;
    }

    /** @return array{cut: array, crease: array, glue: array, labels: array, panels: array, right: float} */
    protected function tubeBody(float $length, float $width, float $height, float $glueWidth): array
    {
        $glue = $this->glueFlapComponent($glueWidth, 0, $glueWidth, $height, 'left');
        $body = [...$glue, 'panels' => [], 'labels' => []];
        $x = $glueWidth;

        foreach ([$length, $width, $length, $width] as $index => $span) {
            $body['panels'][] = ['x' => $x, 'span' => $span];
            $body['labels'][] = $this->label($x + $span / 2, $height / 2, $index % 2 === 0 ? 'Main panel' : 'Side panel');
            $x += $span;
            $body['crease'][] = $this->line($x, 0, $x, $height);
        }

        array_pop($body['crease']);
        $body['cut'][] = $this->line($x, 0, $x, $height);
        $body['right'] = $x;

        return $body;
    }

    /** @return array<int, array{x1: float, y1: float, x2: float, y2: float}> */
    protected function circularCutout(float $x, float $y, float $radius): array
    {
        $lines = [];

        for ($segment = 0; $segment < 64; $segment++) {
            $start = 2 * M_PI * $segment / 64;
            $end = 2 * M_PI * ($segment + 1) / 64;
            $lines[] = $this->line($x + cos($start) * $radius, $y + sin($start) * $radius, $x + cos($end) * $radius, $y + sin($end) * $radius);
        }

        return $lines;
    }

    /** @return array<int, array{x1: float, y1: float, x2: float, y2: float}> */
    protected function handleCutout(float $x, float $y, float $width, float $height): array
    {
        $radius = min($width, $height) / 2;

        return $this->drawPath($x, $y, 'top', fn (DielinePath $path): DielinePath => $path
            ->moveTo($radius, 0)->lineTo($width - $radius, 0)
            ->arcTo($width, 0, $width, $radius, $radius)
            ->lineTo($width, $height - $radius)
            ->arcTo($width, $height, $width - $radius, $height, $radius)
            ->lineTo($radius, $height)
            ->arcTo(0, $height, 0, $height - $radius, $radius)
            ->lineTo(0, $radius)
            ->arcTo(0, 0, $radius, 0, $radius));
    }

    /**
     * Bounds follow the actual geometry, including customized tabs and cutouts.
     *
     * @param  array<int, array{cut: array, crease: array, glue?: array}>  $components
     * @param  array<int, array{x: float, y: float, text: string}>  $labels
     * @return array<string, mixed>
     */
    protected function panelGeometry(array $components, array $labels = []): array
    {
        $layers = ['cut' => [], 'crease' => [], 'cut_crease' => [], 'perforation' => [], 'glue' => [], 'bleed' => []];

        foreach ($components as $component) {
            foreach (array_keys($layers) as $layer) {
                $layers[$layer] = [...$layers[$layer], ...($component[$layer] ?? [])];
            }
        }

        $xs = [];
        $ys = [];

        foreach ([...$layers['cut'], ...$layers['crease'], ...$layers['cut_crease'], ...$layers['perforation']] as $line) {
            $xs = [...$xs, $line['x1'], $line['x2']];
            $ys = [...$ys, $line['y1'], $line['y2']];
        }

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => ['x' => min($xs), 'y' => min($ys), 'width' => max($xs) - min($xs), 'height' => max($ys) - min($ys)],
            'layers' => $layers,
            'labels' => $labels,
        ];
    }
}

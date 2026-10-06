<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;

class FullOverlapCartonTemplate implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    public function key(): string
    {
        return 'full-overlap-carton';
    }

    public function name(): string
    {
        return 'Full-Overlap Food & Dry Goods Carton';
    }

    public function description(): string
    {
        return 'A long-seam-glued carton with opposing full-face flaps that overlap at the top and bottom to double end coverage.';
    }

    public function defaults(): array
    {
        return [
            'l' => 50,
            'w' => 80,
            'h' => 100,
            'glue_flap' => 15,
            'flap_height' => 50,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'glue_flap', 'label' => 'Glue flap width', 'default' => 15, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'flap_height', 'label' => 'Flap height', 'default' => 50, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    public function generate(array $dimensions): array
    {
        $defaults = $this->defaults();
        $horizontalWidth = $this->dimension($dimensions, 'w', $defaults['w']);
        $horizontalLength = $this->dimension($dimensions, 'l', $defaults['l']);
        $height = $this->dimension($dimensions, 'h', $defaults['h']);
        $glueWidth = $this->dimension($dimensions, 'glue_flap', $defaults['glue_flap']);
        $flapHeight = $this->dimension($dimensions, 'flap_height', $defaults['flap_height']);

        $bodyX = $glueWidth;
        $firstPanelEnd = $bodyX + $horizontalWidth;
        $secondPanelEnd = $firstPanelEnd + $horizontalLength;
        $thirdPanelEnd = $secondPanelEnd + $horizontalWidth;
        $bodyRight = $thirdPanelEnd + $horizontalLength;
        $topWidthPanelFirst = $this->closurePanel($bodyX, 0, $horizontalWidth, $flapHeight, 'top');
        $topLengthPanelFirst = $this->closurePanel($firstPanelEnd, 0, $horizontalLength, $flapHeight, 'top');
        $topWidthPanelSecond = $this->closurePanel($secondPanelEnd, 0, $horizontalWidth, $flapHeight, 'top');
        $topLengthPanelSecond = $this->closurePanel($thirdPanelEnd, 0, $horizontalLength, $flapHeight, 'top');
        $bottomWidthPanelFirst = $this->closurePanel($bodyX, $height, $horizontalWidth, $flapHeight, 'bottom');
        $bottomLengthPanelFirst = $this->closurePanel($firstPanelEnd, $height, $horizontalLength, $flapHeight, 'bottom');
        $bottomWidthPanelSecond = $this->closurePanel($secondPanelEnd, $height, $horizontalWidth, $flapHeight, 'bottom');
        $bottomLengthPanelSecond = $this->closurePanel($thirdPanelEnd, $height, $horizontalLength, $flapHeight, 'bottom');
        $glueFlap = $this->glueFlapComponent($bodyX, 0, $glueWidth, $height, 'left');
        $cuts = $this->uniqueLines([
            $this->line($bodyRight, 0, $bodyRight, $height),
            ...$topWidthPanelFirst['cut'],
            ...$topLengthPanelFirst['cut'],
            ...$topWidthPanelSecond['cut'],
            ...$topLengthPanelSecond['cut'],
            ...$bottomWidthPanelFirst['cut'],
            ...$bottomLengthPanelFirst['cut'],
            ...$bottomWidthPanelSecond['cut'],
            ...$bottomLengthPanelSecond['cut'],
            ...$glueFlap['cut'],
        ]);

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => [
                'x' => 0.0,
                'y' => -$flapHeight,
                'width' => $bodyRight,
                'height' => $height + ($flapHeight * 2),
            ],
            'layers' => [
                'cut' => $cuts,
                'crease' => [
                    $this->line($firstPanelEnd, 0, $firstPanelEnd, $height),
                    $this->line($secondPanelEnd, 0, $secondPanelEnd, $height),
                    $this->line($thirdPanelEnd, 0, $thirdPanelEnd, $height),
                    ...$topWidthPanelFirst['crease'],
                    ...$topLengthPanelFirst['crease'],
                    ...$topWidthPanelSecond['crease'],
                    ...$topLengthPanelSecond['crease'],
                    ...$bottomWidthPanelFirst['crease'],
                    ...$bottomLengthPanelFirst['crease'],
                    ...$bottomWidthPanelSecond['crease'],
                    ...$bottomLengthPanelSecond['crease'],
                    ...$glueFlap['crease'],
                ],
                'glue' => $glueFlap['glue'],
                'bleed' => [],
            ],
            'labels' => [
                $this->label($bodyX + ($horizontalWidth / 2), $height / 2, 'Width'),
                $this->label($firstPanelEnd + ($horizontalLength / 2), $height / 2, 'Length'),
                $this->label($secondPanelEnd + ($horizontalWidth / 2), $height / 2, 'Width'),
                $this->label($thirdPanelEnd + ($horizontalLength / 2), $height / 2, 'Length'),
            ],
        ];
    }

    /**
     * Remove duplicate shared edges where adjacent closure flaps meet.
     *
     * @param  array<int, array{x1: float, y1: float, x2: float, y2: float}>  $lines
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    private function uniqueLines(array $lines): array
    {
        $unique = [];
        $seen = [];

        foreach ($lines as $line) {
            $start = [$line['x1'], $line['y1']];
            $end = [$line['x2'], $line['y2']];
            $forward = implode(',', [...$start, ...$end]);
            $reverse = implode(',', [...$end, ...$start]);
            $key = min($forward, $reverse);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $line;
        }

        return $unique;
    }
}

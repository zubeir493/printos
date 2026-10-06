<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;

class AutoBottomTuckTopTemplate implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    public function key(): string
    {
        return 'auto-bottom-tuck-top';
    }

    public function name(): string
    {
        return 'Auto-Bottom, Tuck-Top Carton';
    }

    public function description(): string
    {
        return 'A long-seam-glued carton with a tuck top and pre-glued diagonal bottom wings that close when the carton is squared (ECMA A60.20 family).';
    }

    public function defaults(): array
    {
        return [
            'l' => 70,
            'w' => 45,
            'h' => 110,
            'tuck_flap' => 18,
            'tuck_radius' => 5,
            'dust_flap' => 20,
            'glue_flap' => 12,
            'board_thickness' => 0.45,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'tuck_flap', 'label' => 'Tuck flap height', 'default' => 18, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'tuck_radius', 'label' => 'Tuck radius', 'default' => 5, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'dust_flap', 'label' => 'Dust flap depth', 'default' => 20, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'glue_flap', 'label' => 'Glue flap width', 'default' => 12, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 0.45, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    public function generate(array $dimensions): array
    {
        $defaults = $this->defaults();
        $length = $this->dimension($dimensions, 'l', $defaults['l']);
        $width = $this->dimension($dimensions, 'w', $defaults['w']);
        $height = $this->dimension($dimensions, 'h', $defaults['h']);
        $tuckHeight = $this->dimension($dimensions, 'tuck_flap', $defaults['tuck_flap']);
        $radius = $this->dimension($dimensions, 'tuck_radius', $defaults['tuck_radius']);
        $dustDepth = $this->dimension($dimensions, 'dust_flap', $defaults['dust_flap']);
        $glueWidth = $this->dimension($dimensions, 'glue_flap', $defaults['glue_flap']);
        $boardThickness = $this->dimension($dimensions, 'board_thickness', $defaults['board_thickness']);

        $bodyX = $glueWidth;
        $firstWidthX = $bodyX + $length;
        $secondLengthX = $firstWidthX + $width;
        $secondWidthX = $secondLengthX + $length;
        $bodyRight = $secondWidthX + $width;
        $closureHeight = $width;
        $topClosureY = -$closureHeight - 1;
        $topClosure = $this->tuckFlapComponent(
            $bodyX,
            $topClosureY,
            $length,
            $closureHeight,
            'top',
            $tuckHeight,
            $radius,
        );
        $topDustFlaps = [
            $this->dustFlap($firstWidthX, 0, $width, $dustDepth, 'top'),
            $this->dustFlap($secondWidthX, 0, $width, $dustDepth, 'top'),
        ];

        $bottomFlapDepth = $width * 0.5;
        $bottomRearFlap = $this->closurePanel($bodyX, $height, $length, $bottomFlapDepth, 'bottom');
        $bottomFrontFlap = $this->closurePanel($secondLengthX, $height, $length, $bottomFlapDepth, 'bottom');
        $bottomSideDepth = $length * 0.5;
        $bottomSideFlaps = [
            $this->crashLockWing($firstWidthX, $height, $width, $bottomSideDepth, 'bottom', $boardThickness),
            $this->crashLockWing($secondWidthX, $height, $width, $bottomSideDepth, 'bottom', $boardThickness),
        ];
        $gluePatchWidth = min($length * 0.16, max($boardThickness * 3, $length * 0.08));
        $gluePatchHeight = min($bottomFlapDepth * 0.35, max($boardThickness * 3, $bottomFlapDepth * 0.15));
        $gluePatchTop = $height + min($bottomFlapDepth * 0.2, max($boardThickness, $bottomFlapDepth * 0.1));
        $gluePatchLeft = min($length * 0.06, max($boardThickness, $length * 0.03));
        $glue = [
            $this->gluePatch($bodyX + $gluePatchLeft, $gluePatchTop, $gluePatchWidth, $gluePatchHeight),
            $this->gluePatch($bodyX + $length - $gluePatchLeft - $gluePatchWidth, $gluePatchTop, $gluePatchWidth, $gluePatchHeight),
            $this->gluePatch($secondLengthX + $gluePatchLeft, $gluePatchTop, $gluePatchWidth, $gluePatchHeight),
            $this->gluePatch($secondLengthX + $length - $gluePatchLeft - $gluePatchWidth, $gluePatchTop, $gluePatchWidth, $gluePatchHeight),
        ];
        $glueFlap = $this->glueFlapComponent($bodyX, 0, $glueWidth, $height, 'left');

        $cut = [
            $this->line($secondLengthX, 0, $secondWidthX, 0),
            $this->line($bodyRight, 0, $bodyRight, $height),
            ...$topClosure['cut'],
            ...$bottomRearFlap['cut'],
            ...$bottomFrontFlap['cut'],
            ...$glueFlap['cut'],
        ];
        $crease = [
            $this->line($bodyX, $height, $firstWidthX, $height),
            $this->line($bodyX + $length, 0, $bodyX + $length, $height),
            $this->line($secondLengthX, 0, $secondLengthX, $height),
            $this->line($secondWidthX, 0, $secondWidthX, $height),
            ...$topClosure['crease'],
            ...$bottomRearFlap['crease'],
            ...$bottomFrontFlap['crease'],
            ...$glueFlap['crease'],
        ];

        foreach ($topDustFlaps as $flap) {
            $cut = [...$cut, ...$flap['cut']];
            $crease = [...$crease, ...$flap['crease']];
        }

        foreach ($bottomSideFlaps as $flap) {
            $cut = [...$cut, ...$flap['cut']];
            $crease = [...$crease, ...$flap['crease']];
        }

        $minimumY = min($topClosureY - $tuckHeight + 1, -$dustDepth);
        $maximumY = $height + max($bottomFlapDepth, $bottomSideDepth);

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => [
                'x' => 0.0,
                'y' => $minimumY,
                'width' => $bodyRight,
                'height' => $maximumY - $minimumY,
            ],
            'layers' => [
                'cut' => $cut,
                'crease' => $crease,
                'glue' => [...$glueFlap['glue'], ...$glue],
                'bleed' => [],
            ],
            'labels' => [
                $this->label($bodyX + ($length / 2), $height / 2, 'Length'),
                $this->label($firstWidthX + ($width / 2), $height / 2, 'Width'),
                $this->label($secondLengthX + ($length / 2), $height / 2, 'Length'),
                $this->label($secondWidthX + ($width / 2), $height / 2, 'Width'),
            ],
        ];
    }

    /**
     * @return array{points: array<int, array{x: float, y: float}>}
     */
    private function gluePatch(float $x, float $y, float $width, float $height): array
    {
        return [
            'points' => [
                ['x' => $x, 'y' => $y],
                ['x' => $x + $width, 'y' => $y],
                ['x' => $x + $width, 'y' => $y + $height],
                ['x' => $x, 'y' => $y + $height],
            ],
        ];
    }
}

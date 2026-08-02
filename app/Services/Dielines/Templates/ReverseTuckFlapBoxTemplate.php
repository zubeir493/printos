<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;

class ReverseTuckFlapBoxTemplate implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    private const TUCK_SLIT_CENTER_OFFSET = 1.0;

    public function key(): string
    {
        return 'reverse-tuck-flap-box';
    }

    public function name(): string
    {
        return 'Reverse Tuck Flap Box';
    }

    public function description(): string
    {
        return 'Reverse tuck flap box with four directional dust flaps and two closure-panel tuck-flap assemblies.';
    }

    public function defaults(): array
    {
        return [
            'l' => 200,
            'w' => 50,
            'h' => 100,
            'tuck_flap' => 15,
            'tuck_radius' => 6,
            'dust_flap' => 100,
            'glue_flap' => 20,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'tuck_flap', 'label' => 'Tuck flap height', 'default' => 15, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'tuck_radius', 'label' => 'Tuck radius', 'default' => 6, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'dust_flap', 'label' => 'Dust flap depth', 'default' => 100, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'glue_flap', 'label' => 'Glue flap width', 'default' => 20, 'min' => 0, 'suffix' => 'mm'],
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
        $dustDepth = $this->dimension($dimensions, 'dust_flap', $length * 0.5);
        $glueWidth = $this->dimension($dimensions, 'glue_flap', $defaults['glue_flap']);

        $bodyX = $glueWidth;
        $firstWidthX = $bodyX + $length;
        $secondLengthX = $firstWidthX + $width;
        $secondWidthX = $secondLengthX + $length;
        $bodyRight = $secondWidthX + $width;
        $closureHeight = $width;
        $creaseOffset = self::TUCK_SLIT_CENTER_OFFSET;
        $topClosureY = -$closureHeight - $creaseOffset;
        $bottomClosureY = $height + $closureHeight + $creaseOffset;

        // Dust flaps 1 and 3 are horizontally mirrored to match the reference.
        $dustFlaps = [
            $this->dustFlap($firstWidthX, 0, $width, $dustDepth, 'top', true),
            $this->dustFlap($firstWidthX, $height, $width, $dustDepth, 'bottom'),
            $this->dustFlap($secondWidthX, 0, $width, $dustDepth, 'top'),
            $this->dustFlap($secondWidthX, $height, $width, $dustDepth, 'bottom', true),
        ];

        // The top closure is attached to the second Length panel.
        $topClosure = $this->tuckFlapComponent(
            x: $secondLengthX,
            y: $topClosureY,
            span: $length,
            closureHeight: $closureHeight,
            direction: 'top',
            tuckHeight: $tuckHeight,
            radius: $radius,
        );

        // The bottom closure is attached to the first Length panel.
        $bottomClosure = $this->tuckFlapComponent(
            x: $bodyX,
            y: $bottomClosureY,
            span: $length,
            closureHeight: $closureHeight,
            direction: 'bottom',
            tuckHeight: $tuckHeight,
            radius: $radius,
        );

        $glueFlap = $this->glueFlapComponent(
            x: $bodyX,
            y: 0,
            width: $glueWidth,
            height: $height,
            direction: 'left',
        );

        $cut = [
            $this->line($bodyRight, 0, $bodyRight, $height),
            $this->line($bodyX, 0, $firstWidthX, 0),
            $this->line($secondLengthX, $height, $secondWidthX, $height),
            ...$topClosure['cut'],
            ...$bottomClosure['cut'],
            ...$glueFlap['cut'],
        ];
        $crease = [
            $this->line($bodyX, $height, $firstWidthX, $height),
            $this->line($bodyX + $length, 0, $bodyX + $length, $height),
            $this->line($secondLengthX, 0, $secondLengthX, $height),
            $this->line($secondWidthX, 0, $secondWidthX, $height),
            ...$topClosure['crease'],
            ...$bottomClosure['crease'],
            ...$glueFlap['crease'],
        ];

        foreach ($dustFlaps as $dustFlap) {
            $cut = [...$cut, ...$dustFlap['cut']];
            $crease = [...$crease, ...$dustFlap['crease']];
        }

        $minimumY = $topClosureY - $tuckHeight;
        $maximumY = $bottomClosureY + $tuckHeight;

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
                'glue' => $glueFlap['glue'],
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
}

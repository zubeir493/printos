<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;

class Fefco0210Template implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    public function key(): string
    {
        return 'fefco-0210';
    }

    public function name(): string
    {
        return 'FEFCO 0210';
    }

    public function description(): string
    {
        return 'Slotted retail carton with top and bottom tuck flaps from the same side.';
    }

    public function defaults(): array
    {
        return [
            'l' => 160,
            'w' => 50,
            'h' => 90,
            'tuck_flap' => 28,
            'glue_flap' => 18,
            'dust_flap' => 25,
            'bleed' => 3,
            'board_thickness' => 1.5,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'tuck_flap', 'label' => 'Tuck flap', 'default' => 28, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'glue_flap', 'label' => 'Glue flap', 'default' => 18, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'dust_flap', 'label' => 'Dust flap', 'default' => 25, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'bleed', 'label' => 'Bleed', 'default' => 3, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 1.5, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    public function generate(array $dimensions): array
    {
        $defaults = $this->defaults();
        $length = $this->dimension($dimensions, 'l', $defaults['l']);
        $width = $this->dimension($dimensions, 'w', $defaults['w']);
        $height = $this->dimension($dimensions, 'h', $defaults['h']);
        $tuckFlap = $this->dimension($dimensions, 'tuck_flap', $defaults['tuck_flap']);
        $glueFlap = $this->dimension($dimensions, 'glue_flap', $defaults['glue_flap']);
        $dustFlap = $this->dimension($dimensions, 'dust_flap', $defaults['dust_flap']);
        $bleed = $this->dimension($dimensions, 'bleed', $defaults['bleed']);

        $panelWidths = [$glueFlap, $width, $length, $width, $length];
        $x = 0.0;
        $foldX = [];

        foreach ($panelWidths as $panelWidth) {
            $x += $panelWidth;
            $foldX[] = $x;
        }

        $bodyTop = $tuckFlap;
        $bodyBottom = $bodyTop + $height;
        $totalWidth = array_sum($panelWidths);
        $totalHeight = $height + ($tuckFlap * 2);

        $cut = [
            $this->line(0, $bodyTop, $totalWidth, $bodyTop),
            $this->line($totalWidth, $bodyTop, $totalWidth, $bodyBottom),
            $this->line($totalWidth, $bodyBottom, 0, $bodyBottom),
            $this->line(0, $bodyBottom, 0, $bodyTop),
        ];

        $panelStart = $glueFlap;
        foreach ([$width, $length, $width, $length] as $index => $panelWidth) {
            $flapDepth = in_array($index, [1, 3], true) ? $tuckFlap : $dustFlap;
            $cut[] = $this->line($panelStart, $bodyTop, $panelStart, $bodyTop - $flapDepth);
            $cut[] = $this->line($panelStart, $bodyTop - $flapDepth, $panelStart + $panelWidth, $bodyTop - $flapDepth);
            $cut[] = $this->line($panelStart + $panelWidth, $bodyTop - $flapDepth, $panelStart + $panelWidth, $bodyTop);
            $cut[] = $this->line($panelStart, $bodyBottom, $panelStart, $bodyBottom + $flapDepth);
            $cut[] = $this->line($panelStart, $bodyBottom + $flapDepth, $panelStart + $panelWidth, $bodyBottom + $flapDepth);
            $cut[] = $this->line($panelStart + $panelWidth, $bodyBottom + $flapDepth, $panelStart + $panelWidth, $bodyBottom);
            $panelStart += $panelWidth;
        }

        $crease = [
            $this->line($glueFlap, 0, $glueFlap, $totalHeight),
            $this->line($foldX[1], 0, $foldX[1], $totalHeight),
            $this->line($foldX[2], 0, $foldX[2], $totalHeight),
            $this->line($foldX[3], 0, $foldX[3], $totalHeight),
            $this->line(0, $bodyTop, $totalWidth, $bodyTop),
            $this->line(0, $bodyBottom, $totalWidth, $bodyBottom),
        ];

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => ['width' => $totalWidth, 'height' => $totalHeight],
            'layers' => [
                'cut' => $cut,
                'crease' => $crease,
                'glue' => [
                    ['points' => $this->polygon(0, $bodyTop, $glueFlap, $height)],
                ],
                'bleed' => [
                    $this->line(-$bleed, $bodyTop - $bleed, $totalWidth + $bleed, $bodyTop - $bleed),
                    $this->line($totalWidth + $bleed, $bodyTop - $bleed, $totalWidth + $bleed, $bodyBottom + $bleed),
                    $this->line($totalWidth + $bleed, $bodyBottom + $bleed, -$bleed, $bodyBottom + $bleed),
                    $this->line(-$bleed, $bodyBottom + $bleed, -$bleed, $bodyTop - $bleed),
                ],
            ],
            'labels' => [
                $this->label($glueFlap + ($width / 2), $bodyTop + ($height / 2), 'Side'),
                $this->label($glueFlap + $width + ($length / 2), $bodyTop + ($height / 2), 'Front'),
                $this->label($glueFlap + $width + $length + ($width / 2), $bodyTop + ($height / 2), 'Side'),
                $this->label($glueFlap + ($width * 2) + $length + ($length / 2), $bodyTop + ($height / 2), 'Back'),
            ],
        ];
    }
}

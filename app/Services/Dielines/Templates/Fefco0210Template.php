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
            ['key' => 'tuck_flap', 'label' => 'Tuck flap', 'default' => 15, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'glue_flap', 'label' => 'Glue flap', 'default' => 15, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'dust_flap', 'label' => 'Dust flap', 'default' => 50, 'min' => 1, 'suffix' => 'mm'],
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

        $cut = $this->rectangle(0, $bodyTop, $totalWidth, $height);

        $panelStart = $glueFlap;
        foreach ([$width, $length, $width, $length] as $index => $panelWidth) {
            $isTuckPanel = in_array($index, [1, 3], true);

            if ($isTuckPanel) {
                array_push(
                    $cut,
                    ...$this->straightFlap($panelStart, $bodyTop, $panelWidth, $tuckFlap, 'top'),
                    ...$this->straightFlap($panelStart, $bodyBottom, $panelWidth, $tuckFlap, 'bottom'),
                );
            } else {
                $topDustFlap = $this->dustFlap($panelStart, $bodyTop, $panelWidth, $dustFlap, 'top');
                $bottomDustFlap = $this->dustFlap($panelStart, $bodyBottom, $panelWidth, $dustFlap, 'bottom');

                array_push(
                    $cut,
                    ...$topDustFlap['cut'],
                    ...$bottomDustFlap['cut'],
                );
            }

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
                    $this->glueArea(0, $bodyTop, $glueFlap, $height),
                ],
                'bleed' => $this->bleedBox(0, $bodyTop, $totalWidth, $height, $bleed),
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

<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;

class SleeveBoxTemplate implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    public function key(): string
    {
        return 'open-ended-sleeve';
    }

    public function name(): string
    {
        return 'Open-Ended Sleeve';
    }

    public function description(): string
    {
        return 'A glued paperboard tube open at both ends that slides over a tray, inner carton, or product.';
    }

    public function defaults(): array
    {
        return [
            'l' => 80,
            'w' => 50,
            'h' => 40,
            'glue_flap' => 15,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'glue_flap', 'label' => 'Glue flap width', 'default' => 15, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    public function generate(array $dimensions): array
    {
        $defaults = $this->defaults();
        $length = $this->dimension($dimensions, 'l', $defaults['l']);
        $width = $this->dimension($dimensions, 'w', $defaults['w']);
        $height = $this->dimension($dimensions, 'h', $defaults['h']);
        $glueWidth = $this->dimension($dimensions, 'glue_flap', $defaults['glue_flap']);

        $bodyX = $glueWidth;
        $firstWidthX = $bodyX + $length;
        $secondLengthX = $firstWidthX + $width;
        $secondWidthX = $secondLengthX + $length;
        $bodyRight = $secondWidthX + $width;
        $glueFlap = $this->glueFlapComponent($bodyX, 0, $glueWidth, $height, 'left');

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => [
                'x' => 0.0,
                'y' => 0.0,
                'width' => $bodyRight,
                'height' => $height,
            ],
            'layers' => [
                'cut' => [
                    $this->line($bodyX, 0, $bodyRight, 0),
                    $this->line($bodyRight, 0, $bodyRight, $height),
                    $this->line($bodyRight, $height, $bodyX, $height),
                    ...$glueFlap['cut'],
                ],
                'crease' => [
                    $this->line($bodyX + $length, 0, $bodyX + $length, $height),
                    $this->line($secondLengthX, 0, $secondLengthX, $height),
                    $this->line($secondWidthX, 0, $secondWidthX, $height),
                    ...$glueFlap['crease'],
                ],
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

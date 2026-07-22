<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;
use App\Services\Dielines\Geometry\DielineCanvas;

class RoundedTuckCartonTemplate implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    public function key(): string
    {
        return 'rounded-tuck-carton';
    }

    public function name(): string
    {
        return 'Rounded Tuck Carton';
    }

    public function description(): string
    {
        return 'Canvas-style starter dieline with a rounded tuck flap.';
    }

    public function defaults(): array
    {
        return [
            'l' => 200,
            'w' => 50,
            'h' => 100,
            'tuck_radius' => 30,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'tuck_radius', 'label' => 'Tuck radius', 'default' => 30, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    public function generate(array $dimensions): array
    {
        $defaults = $this->defaults();
        $width = $this->dimension($dimensions, 'l', $defaults['l']);
        $height = $this->dimension($dimensions, 'h', $defaults['h']);
        $radius = $this->dimension($dimensions, 'tuck_radius', $defaults['tuck_radius']);

        $ctx = new DielineCanvas;

        $drawTuckFlaps = function () use ($ctx, $width, $height, $radius): void {
            $ctx->beginPath();

            $ctx->moveTo(0, 0);

            $ctx->lineTo(0, -$height + $radius);
            $ctx->arcTo(0, -$height, $radius, -$height, $radius);

            $ctx->lineTo($width - $radius, -$height);

            $ctx->arcTo($width, -$height, $width, -$height + $radius, $radius);
            $ctx->lineTo($width, 0);

            $ctx->moveTo(0, -$height + $radius - 5);
            $ctx->lineTo(25, -$height + $radius - 5);
            $ctx->lineTo(25, -$height + $radius + 5);

            $ctx->moveTo($width, -$height + $radius - 5);
            $ctx->lineTo($width - 25, -$height + $radius - 5);
            $ctx->lineTo($width - 25, -$height + $radius + 5);

            $ctx->strokeStyle = 'red';
            $ctx->stroke();

            $ctx->beginPath();
            $ctx->moveTo(25, -$height + $radius);
            $ctx->lineTo($width - 25, -$height + $radius);
            $ctx->strokeStyle = 'black';
            $ctx->stroke();
        };

        $ctx->beginPath();
        $ctx->strokeStyle = 'red';
        $ctx->moveTo(0, $height + 25);
        $ctx->lineTo(35, $height);
        $ctx->lineTo(35 + $width, $height);
        $ctx->stroke();

        $ctx->save();
        $ctx->translate(35 + $width, $height);
        $drawTuckFlaps();
        $ctx->restore();

        $layers = $ctx->layers();

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => ['width' => (35 + ($width * 2)), 'height' => ($height + 25)],
            'layers' => [
                'cut' => $layers['cut'],
                'crease' => $layers['crease'],
                'glue' => [],
                'bleed' => [],
            ],
            'labels' => [
                $this->label(35 + $width + ($width / 2), $height / 2, 'Tuck'),
            ],
        ];
    }
}

<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;

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
        return 'Rounded tuck carton with a closure panel, dust flap, and tapered glue flap.';
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
        $width = $this->dimension($dimensions, 'l', $defaults['l']);
        $height = $this->dimension($dimensions, 'h', $defaults['h']);
        $tuckHeight = $this->dimension($dimensions, 'tuck_flap', $defaults['tuck_flap']);
        $radius = $this->dimension($dimensions, 'tuck_radius', $defaults['tuck_radius']);
        $dustDepth = $this->dimension($dimensions, 'dust_flap', $width * 0.5);
        $glueWidth = $this->dimension($dimensions, 'glue_flap', $defaults['glue_flap']);
        $closureHeight = $width;
        $panelX = 35 + $width;
        $hingeY = $height;
        $tuckY = $hingeY - $closureHeight - 1.0;

        $tuckFlap = $this->tuckFlapComponent(
            x: $panelX,
            y: $tuckY,
            span: $width,
            closureHeight: $closureHeight,
            direction: 'top',
            tuckHeight: $tuckHeight,
            radius: $radius,
        );

        $dustFlap = $this->dustFlap(
            x: 35,
            y: $hingeY,
            span: $width,
            depth: $dustDepth,
            direction: 'top',
            flipHorizontal: true,
        );

        $glueFlap = $this->glueFlapComponent(
            x: 35,
            y: $hingeY,
            width: $glueWidth,
            height: $closureHeight,
            direction: 'left',
        );

        $cut = array_merge($tuckFlap['cut'], $dustFlap['cut'], $glueFlap['cut']);
        $crease = array_merge($tuckFlap['crease'], $dustFlap['crease'], $glueFlap['crease']);

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => [
                'x' => 0.0,
                'y' => $tuckY - $tuckHeight,
                'width' => (35 + ($width * 2)),
                'height' => ($height + $closureHeight) - ($tuckY - $tuckHeight),
            ],
            'layers' => [
                'cut' => $cut,
                'crease' => $crease,
                'glue' => $glueFlap['glue'],
                'bleed' => [],
            ],
            'labels' => [
                $this->label($panelX + ($width / 2), $tuckY - ($tuckHeight / 2), 'Tuck Flap'),
                $this->label($panelX + ($width / 2), $tuckY + 1 + ($closureHeight / 2), 'Closure Panel'),
                $this->label(35 + ($width / 2), $hingeY - ($dustDepth / 2), 'Dust'),
            ],
        ];
    }
}

<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsPrecisionGeometry;
use App\Services\Dielines\DielineTemplateContract;
use App\Services\Dielines\Geometry\PanelProfile;

class HangTabCartonTemplate implements DielineTemplateContract
{
    use BuildsPrecisionGeometry;

    public function key(): string
    {
        return 'hang-tab-carton';
    }

    public function name(): string
    {
        return 'Hang Tab Carton';
    }

    public function description(): string
    {
        return 'A pharmacy carton with tuck closures, relieved dust flaps, and two reinforced hanging panels with matching Euro slots along the joining edge.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/68447/tuck-end-boxes-becf-10501';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_6844720260912-2-u342aj.svg';
    }

    public function defaults(): array
    {
        return ['l' => 60, 'w' => 30, 'h' => 100, 'hanger_height' => 25, 'hanger_slot_width' => 30, 'hanger_slot_height' => 5, 'hanger_hole_diameter' => 8, 'tuck_flap' => 12, 'dust_flap' => 20];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'hanger_height', 'label' => 'Hanging panel depth', 'default' => 50, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'hanger_slot_width', 'label' => 'Euro slot length', 'default' => 40, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'hanger_slot_height', 'label' => 'Euro slot thickness', 'default' => 6, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'hanger_hole_diameter', 'label' => 'Euro slot centre diameter', 'default' => 10, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'tuck_flap', 'label' => 'Tuck flap height', 'default' => 15, 'min' => 2, 'suffix' => 'mm'],
            ['key' => 'dust_flap', 'label' => 'Dust flap depth', 'default' => 32, 'min' => 1, 'suffix' => 'mm'],
        ];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return [
            'dust_flap' => ['max:'.$v['w']],
            'hanger_slot_width' => ['min:'.max($v['hanger_hole_diameter'] + 2, $v['hanger_slot_height']), 'max:'.($v['h'] * 0.8)],
            'hanger_slot_height' => ['max:'.($v['hanger_height'] * 0.3)],
            'hanger_hole_diameter' => ['max:'.($v['hanger_height'] * 0.6)],
        ];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        $length = (float) $v['l'];
        $width = (float) $v['w'];
        $height = (float) $v['h'];
        $hanger = (float) $v['hanger_height'];
        $right = 2 * ($length + $width);
        $columns = [[-50, -$hanger], [0, 0], [100, $length], [150, $length + $width], [250, 2 * $length + $width], [300, $right], [350, $right + $hanger]];
        $layers = (new PanelProfile)->project('hang-tab-carton', function (float $x, float $y) use ($columns, $height, $width, $v): array {
            $top = $y < -150;
            $depth = $top ? -150 - $y : $y;

            if ($depth <= 0) {
                $projectedY = $y * $height / 150;
            } else {
                $major = $x >= 150 && $x <= 250;
                $projectedDepth = $major
                    ? PanelProfile::station($depth, [[0, 0], [49, $width - 1], [50, $width], [64, $width + $v['tuck_flap'] - 1]])
                    : $depth * $v['dust_flap'] / 32;
                $projectedY = $top ? -$height - $projectedDepth : $projectedDepth;
            }

            return [PanelProfile::station($x, $columns), $projectedY];
        }, [-150, 0], array_column($columns, 0));
        $slot = $this->euroSlot(-$hanger * 0.6, -$height / 2, (float) $v['hanger_slot_width'], (float) $v['hanger_slot_height'], (float) $v['hanger_hole_diameter'] / 2);
        $layers['cut'] = [...$layers['cut'], ...$slot, ...$this->reflectLines($slot, $right / 2)];

        return $this->panelGeometry([$layers], [
            $this->label($length / 2, -$height / 2, 'Front'),
            $this->label($length * 1.5 + $width, -$height / 2, 'Back'),
        ]);
    }

    /** @return array<int, array{x1: float, y1: float, x2: float, y2: float}> */
    private function euroSlot(float $x, float $cy, float $span, float $thickness, float $bulge): array
    {
        $radius = $thickness / 2;
        $top = $cy - ($span - $thickness) / 2;
        $bottom = $cy + ($span - $thickness) / 2;

        return [
            $this->line($x, $top, $x, $cy - $bulge),
            ...$this->precisionArc($x, $cy, $bulge, -M_PI / 2, -3 * M_PI / 2),
            $this->line($x, $cy + $bulge, $x, $bottom),
            ...$this->precisionArc($x + $radius, $bottom, $radius, M_PI, 0),
            $this->line($x + $thickness, $bottom, $x + $thickness, $top),
            ...$this->precisionArc($x + $radius, $top, $radius, 0, -M_PI),
        ];
    }
}

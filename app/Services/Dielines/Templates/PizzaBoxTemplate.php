<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class PizzaBoxTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'pizza-box';
    }

    public function name(): string
    {
        return 'Octagon Pizza Box';
    }

    public function description(): string
    {
        return 'An octagonal pizza carton with diagonal roll-over corner walls, matching floor locks, two steam vents, and a rounded locking lid.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/69137/tray-boxes-becf-21711';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_6913720260912-2-mpnz7t.svg';
    }

    public function defaults(): array
    {
        return ['l' => 300, 'w' => 300, 'h' => 40, 'corner_cut' => 60, 'board_thickness' => 1, 'vent_diameter' => 20];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'corner_cut', 'label' => 'Octagon corner cut', 'default' => 58.5, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 1, 'min' => 0.1, 'suffix' => 'mm'],
            ['key' => 'vent_diameter', 'label' => 'Steam vent diameter', 'default' => 20, 'min' => 1, 'suffix' => 'mm'],
        ];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['corner_cut' => ['max:'.(min($v['l'], $v['w']) * 0.4)], 'board_thickness' => ['max:'.($v['h'] / 10)], 'vent_diameter' => ['max:'.min($v['h'] * .8, $v['l'] * .2, $v['w'] * .2)]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l, 'w' => $w, 'h' => $h, 'corner_cut' => $corner, 'board_thickness' => $board] = $v;
        $rim = -2 * $w - $h + 0.9 * $board;
        $columns = [[-50, -$h], [-49.1, -$h + 0.9 * $board], [0, 0], [0.9, 0.9 * $board], [58.5, $corner], [141.5, $l - $corner], [199.1, $l - 0.9 * $board], [200, $l], [249.1, $l + $h - 0.9 * $board], [250, $l + $h]];
        $rows = [[-499.1, $rim - $h], [-449.1, $rim], [-250, -$w - $h], [-200, -$w], [-199.1, -$w + 0.9 * $board], [-58.5, -$corner], [0, 0], [50, $h]];

        $vents = [];
        foreach ([$l / 4, 3 * $l / 4] as $centre) {
            $vents = [...$vents, ...$this->precisionArc($centre, -$w - $h / 2, $v['vent_diameter'] / 2, 0, 2 * M_PI)];
        }

        return $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$w / 2, 'Pizza base'), $this->label($l / 2, $rim + $w / 2, 'Lid')], $vents);
    }
}

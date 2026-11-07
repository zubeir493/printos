<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class LockTabPizzaBoxTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'lock-tab-pizza-box';
    }

    public function name(): string
    {
        return 'Lock Tab Pizza Box';
    }

    public function description(): string
    {
        return 'A rectangular pizza carton with a double front wall, two floor locking tabs, relieved corner wings, a rounded lid, and matched finger notches.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/48594/tray-boxes-becf-21705';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_4859420260912-2-o3c1sc.svg';
    }

    public function defaults(): array
    {
        return ['l' => 300, 'w' => 300, 'h' => 40, 'board_thickness' => 1, 'vent_diameter' => 16];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 1, 'min' => 0.1, 'suffix' => 'mm'],
            ['key' => 'vent_diameter', 'label' => 'Steam vent diameter', 'default' => 15, 'min' => 1, 'suffix' => 'mm'],
        ];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['board_thickness' => ['max:'.($v['h'] / 10)], 'vent_diameter' => ['max:'.min($v['h'] * .8, $v['l'] * .2, $v['w'] * .2)]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l, 'w' => $w, 'h' => $h, 'board_thickness' => $board] = $v;
        $rim = -2 * $w - $h + 2.7 * $board;
        $columns = [[-50, -$h], [-0.9, -0.9 * $board], [0, 0], [0.9, 0.9 * $board], [199.1, $l - 0.9 * $board], [200, $l], [200.9, $l + 0.9 * $board], [250, $l + $h]];
        $rows = [[-397.3, $rim - $h], [-347.3, $rim], [-201.8, -$w - $h - 1.8 * $board], [-200, -$w - $h], [-150, -$w], [0, 0], [50, $h], [52.7, $h + 2.7 * $board], [101.8, 2 * $h + 1.8 * $board], [104.7, 2 * $h + 4.7 * $board]];

        $vents = [];
        foreach ([$l / 3, 2 * $l / 3] as $centre) {
            $vents = [...$vents, ...$this->precisionArc($centre, -$w - $h / 2, $v['vent_diameter'] / 2, 0, 2 * M_PI)];
        }

        return $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$w / 2, 'Pizza base'), $this->label($l / 2, $rim + $w / 2, 'Lid')], $vents);
    }
}

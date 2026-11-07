<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class FoodTrayTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'four-corner-food-tray';
    }

    public function name(): string
    {
        return 'Hinged Food Tray';
    }

    public function description(): string
    {
        return 'A hinged food tray with matching lid wings, a front tuck and relieved corner tabs.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/51491/tray-boxes-becf-11a09';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_5149120260912-2-jl9kr.svg';
    }

    public function defaults(): array
    {
        return ['l' => 180, 'w' => 120, 'h' => 40, 'tuck_flap' => 15, 'corner_tab' => 20];
    }

    public function advancedFields(): array
    {
        return [['key' => 'tuck_flap', 'label' => 'Lid tuck depth', 'default' => 15, 'min' => 1, 'suffix' => 'mm'], ['key' => 'corner_tab', 'label' => 'Side return depth', 'default' => 20, 'min' => 1, 'suffix' => 'mm']];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['tuck_flap' => ['max:'.$v['h']], 'corner_tab' => ['max:'.$v['h']]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'tuck_flap' => $tuck,'corner_tab' => $wing] = $v;
        $columns = [[-60, -$h - $wing], [-40, -$h], [0, 0], [.5, .5], [149.5, $l - .5], [150, $l], [190, $l + $h], [210, $l + $h + $wing]];
        $rows = [[-235, -2 * $w - $h - $tuck], [-220, -2 * $w - $h], [-130.5, -$w - $h - .5], [-90, -$w], [0, 0], [40, $h]];

        return $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$w / 2, 'Tray base'), $this->label($l / 2, -1.5 * $w - $h, 'Lid')]);
    }
}

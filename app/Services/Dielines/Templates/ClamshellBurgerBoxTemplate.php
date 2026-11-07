<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class ClamshellBurgerBoxTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'clamshell-burger-box';
    }

    public function name(): string
    {
        return 'Clamshell Burger Box';
    }

    public function description(): string
    {
        return 'A hinged clamshell with tapered trays, rounded corner wings, an articulated finger opening, and hooked front and side locks.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/186590/food-boxes-becf-12018';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_18659020260912-2-pw1me0.svg';
    }

    public function baseDimensionLabels(): array
    {
        return ['l' => 'Base length', 'w' => 'Base width', 'h' => 'Base wall height'];
    }

    public function defaults(): array
    {
        return ['l' => 120, 'w' => 120, 'h' => 35, 'lid_depth' => 30];
    }

    public function advancedFields(): array
    {
        return [['key' => 'lid_depth', 'label' => 'Lid wall height', 'default' => 40, 'min' => 1, 'suffix' => 'mm']];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['lid_depth' => ['max:'.$v['h']]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l, 'w' => $w, 'h' => $h, 'lid_depth' => $lid] = $v;
        $lidLeft = $l + $h + $lid;
        $lidSpan = $l - 7.3297769378 * $lid / 40;
        $columns = [[-40, -$h], [0, 0], [120, $l], [160, $l + $h], [200, $lidLeft], [312.6702230622, $lidLeft + $lidSpan], [352.6702230622, $lidLeft + $lidSpan + $lid]];
        $rows = [[-160, -$w - $h], [-120, -$w], [0, 0], [40, $h]];

        return $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$w / 2, 'Burger base'), $this->label($lidLeft + $lidSpan / 2, -$w / 2, 'Lid')]);
    }
}

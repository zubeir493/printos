<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class FlipTopBurgerBoxTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'flip-top-burger-box';
    }

    public function name(): string
    {
        return 'Flip Top Burger Box';
    }

    public function description(): string
    {
        return 'A burger clamshell with tapered side walls, triangular corner locks, an integral double hinge and front thumb relief.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/52208/food-boxes-becf-12012';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_5220820260912-2-feopf6.svg';
    }

    public function defaults(): array
    {
        return ['l' => 110, 'w' => 110, 'h' => 60, 'rim_length' => 125];
    }

    public function advancedFields(): array
    {
        return [['key' => 'rim_length', 'label' => 'Rim length', 'default' => 120, 'min' => 1, 'suffix' => 'mm']];
    }

    public function baseDimensionLabels(): array
    {
        return ['l' => 'Base length', 'w' => 'Base width', 'h' => 'Closed box height'];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['rim_length' => ['min:'.$v['l'], 'max:'.($v['l'] + $v['h'])]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'rim_length' => $rim] = $v;
        $splay = ($rim - $l) / 2;
        $columns = [[-11.25, -$splay], [0, 0], [97.5, $l], [108.75, $l + $splay]];
        $rows = [[-355, -2 * $w - 2 * $h], [-315, -2 * $w - 1.5 * $h], [-275, -2 * $w - $h], [-177.5, -$w - $h], [-137.5, -$w - $h / 2], [-97.5, -$w], [0, 0], [40, $h / 2]];

        return $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$w / 2, 'Burger base'), $this->label($l / 2, -1.5 * $w - $h, 'Lid')]);
    }
}

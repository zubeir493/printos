<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class FullOverlapCartonTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'full-overlap-carton';
    }

    public function name(): string
    {
        return 'Full Overlap Carton';
    }

    public function description(): string
    {
        return 'A full-overlap corrugated carton with equal-depth end flaps, caliper relief slots and a tapered side seam.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/19474/standard-boxes-becf-21d03';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_1947420260912-2-i2dfhw.svg';
    }

    public function defaults(): array
    {
        return ['l' => 180, 'w' => 120, 'h' => 160, 'glue_flap' => 20, 'flap_height' => 119.1];
    }

    public function advancedFields(): array
    {
        return [['key' => 'glue_flap', 'label' => 'Side seam width', 'default' => 20, 'min' => 1, 'suffix' => 'mm'], ['key' => 'flap_height', 'label' => 'Sealing flap depth', 'default' => 119.1, 'min' => 1, 'suffix' => 'mm']];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['glue_flap' => ['max:'.($v['w'] / 2)], 'flap_height' => ['min:'.($v['w'] / 2), 'max:'.$v['w']]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'glue_flap' => $glue,'flap_height' => $flap] = $v;
        $columns = [[-20, -$glue], [0, 0], [100, $l], [150, $l + $w], [250, 2 * $l + $w], [299.1, 2 * ($l + $w) - .9]];
        $rows = [[-199.1, -$h - $flap], [-150, -$h], [0, 0], [49.1, $flap]];
        $geometry = $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$h / 2, 'Front')]);
        $geometry['layers']['glue'] = [['points' => [['x' => -$glue + .5, 'y' => -$h + 6], ['x' => -.5, 'y' => -$h + .5], ['x' => -.5, 'y' => -.5], ['x' => -$glue + .5, 'y' => -6]]]];

        return $geometry;
    }
}

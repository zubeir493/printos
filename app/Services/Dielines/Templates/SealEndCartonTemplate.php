<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class SealEndCartonTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'seal-end-carton';
    }

    public function name(): string
    {
        return 'Seal End Carton';
    }

    public function description(): string
    {
        return 'A four-panel carton with full-depth rectangular sealing flaps at both ends, narrow flap reliefs and a tapered side seam.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/36631/standard-boxes-becf-11d01';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_3663120260912-2-ksixys.svg';
    }

    public function defaults(): array
    {
        return ['l' => 70, 'w' => 35, 'h' => 120, 'glue_flap' => 12, 'seal_flap' => 35];
    }

    public function advancedFields(): array
    {
        return [['key' => 'glue_flap', 'label' => 'Side seam width', 'default' => 13, 'min' => 1, 'suffix' => 'mm'], ['key' => 'seal_flap', 'label' => 'Sealing flap depth', 'default' => 50, 'min' => 1, 'suffix' => 'mm']];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['glue_flap' => ['max:'.($v['w'] / 2)], 'seal_flap' => ['min:'.($v['w'] / 2), 'max:'.$v['w']]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'seal_flap' => $flap] = $v;
        $columns = [[-13, -$v['glue_flap']], [0, 0], [2, 2], [98, $l - 2], [100, $l], [150, $l + $w], [152, $l + $w + 2], [248, 2 * $l + $w - 2], [250, 2 * $l + $w], [299.5, 2 * ($l + $w) - .5]];
        $rows = [[-200, -$h - $flap], [-151, -$h - 1], [-150.5, -$h - .5], [-150, -$h], [0, 0], [.5, .5], [1, 1], [50, $flap]];

        return $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$h / 2, 'Front'), $this->label(1.5 * $l + $w, -$h / 2, 'Back')]);
    }
}

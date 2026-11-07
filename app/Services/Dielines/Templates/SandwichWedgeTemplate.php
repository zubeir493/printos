<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class SandwichWedgeTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'sandwich-wedge';
    }

    public function name(): string
    {
        return 'Triangle Food Carton';
    }

    public function description(): string
    {
        return 'An equilateral triangular prism with triangular end closures, relieved locking tongues and a longitudinal glue flap. Triangle height follows the side length.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/51486/polygonal-boxes-becf-12804';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_5148620260912-2-9qdt1l.svg';
    }

    public function defaults(): array
    {
        return ['l' => 120, 'w' => 100, 'h' => 12];
    }

    public function advancedFields(): array
    {
        return [];
    }

    public function baseDimensionLabels(): array
    {
        return ['l' => 'Prism length', 'w' => 'Triangle side length', 'h' => 'Glue flap depth'];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['h' => ['max:'.min($v['l'] / 4, $v['w'] / 3)]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $glue] = $v;
        $columns = [[-95.7627944163, -$w * sqrt(3) / 2 - .5], [0, 0], [200, $l], [295.7627944163, $l + $w * sqrt(3) / 2 + .5]];
        $rows = [[-329.5, -3 * $w + .5], [-220, -2 * $w], [-165, -1.5 * $w], [-110, -$w], [-55, -$w / 2], [0, 0], [15, $glue]];

        return $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -1.5 * $w, 'Triangle carton')]);
    }
}

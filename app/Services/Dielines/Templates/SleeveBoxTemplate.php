<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsPackagingPanels;
use App\Services\Dielines\DielineTemplateContract;

class SleeveBoxTemplate implements DielineTemplateContract
{
    use BuildsPackagingPanels;

    public function key(): string
    {
        return 'open-ended-sleeve';
    }

    public function name(): string
    {
        return 'Carton Sleeve';
    }

    public function description(): string
    {
        return 'An open-ended sleeve with four caliper-adjusted panels and a tapered glue flap, sized to slide over a tray or product.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.vorexpackaging.ca/tools/dieline-generator/sleeve/';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://www.vorexpackaging.ca/tools/dieline-generator/sleeve/sleeve-dieline-152x102x38-mm.svg';
    }

    public function baseDimensionLabels(): array
    {
        return ['l' => 'Front panel width', 'w' => 'Side panel width', 'h' => 'Sleeve depth'];
    }

    public function defaults(): array
    {
        return ['l' => 150, 'w' => 60, 'h' => 50, 'glue_flap' => 12.7, 'board_thickness' => .4572];
    }

    public function advancedFields(): array
    {
        return [['key' => 'glue_flap', 'label' => 'Glue flap width', 'default' => 12.7, 'min' => 1, 'suffix' => 'mm'], ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => .4572, 'min' => .1, 'suffix' => 'mm']];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['glue_flap' => ['max:'.($v['w'] / 2)], 'board_thickness' => ['max:'.(min($v['l'], $v['w']) / 10)]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'glue_flap' => $glue,'board_thickness' => $stock] = $v;
        $front = $l + $stock;
        $side = $w + $stock;
        $right = 2 * ($front + $side);
        $taper = min($glue * .27, $h * .22);
        $points = [[0, 0], [$right, 0], [$right + $glue, $taper], [$right + $glue, $h - $taper], [$right, $h], [0, $h], [0, 0]];
        $cuts = [];
        foreach (array_slice($points, 1) as $index => $end) {
            $cuts[] = $this->line(...[...$points[$index], ...$end]);
        }
        $folds = [];
        foreach ([$front, $front + $side, 2 * $front + $side, $right] as $x) {
            $folds[] = $this->line($x, 0, $x, $h);
        }

        return $this->panelGeometry([['cut' => $cuts, 'crease' => $folds, 'glue' => [['points' => [['x' => $right, 'y' => 0], ['x' => $right + $glue, 'y' => $taper], ['x' => $right + $glue, 'y' => $h - $taper], ['x' => $right, 'y' => $h]]]]]], [$this->label($front / 2, $h / 2, 'Back'), $this->label(1.5 * $front + $side, $h / 2, 'Front')]);
    }
}

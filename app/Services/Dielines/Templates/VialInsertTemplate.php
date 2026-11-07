<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsPrecisionGeometry;
use App\Services\Dielines\DielineTemplateContract;

class VialInsertTemplate implements DielineTemplateContract
{
    use BuildsPrecisionGeometry;

    public function key(): string
    {
        return 'vial-insert';
    }

    public function name(): string
    {
        return 'Single Aperture Insert';
    }

    public function description(): string
    {
        return 'A raised platform with four tapered support walls and a central circular opening for an individual vial or bottle.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/73670/separators-becf-11f09';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_7367020260912-2-i30sid.svg';
    }

    public function defaults(): array
    {
        return ['l' => 65, 'w' => 45, 'h' => 20, 'vial_diameter' => 20, 'wall_taper' => 6];
    }

    public function advancedFields(): array
    {
        return [['key' => 'vial_diameter', 'label' => 'Opening diameter', 'default' => 20, 'min' => 1, 'suffix' => 'mm'], ['key' => 'wall_taper', 'label' => 'Support wall taper', 'default' => 13, 'min' => 1, 'suffix' => 'mm']];
    }

    public function baseDimensionLabels(): array
    {
        return ['l' => 'Platform length', 'w' => 'Platform width', 'h' => 'Support height'];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['vial_diameter' => ['max:'.(min($v['l'], $v['w']) - 2)], 'wall_taper' => ['max:'.(min($v['l'], $v['w']) / 3)]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'wall_taper' => $taper,'vial_diameter' => $diameter] = $v;
        if ($diameter <= 0 || $diameter > min($l, $w) - 2 || $taper <= 0 || $taper > min($l, $w) / 3) {
            throw new \InvalidArgumentException('The aperture and tapered walls must fit the platform.');
        }
        $points = [[0, 0], [$taper, $h], [$l - $taper, $h], [$l, 0], [$l + $h, -$taper], [$l + $h, -$w + $taper], [$l, -$w], [$l - $taper, -$w - $h], [$taper, -$w - $h], [0, -$w], [-$h, -$w + $taper], [-$h, -$taper], [0, 0]];
        $cuts = [];
        foreach (array_slice($points, 1) as $index => $end) {
            $cuts[] = $this->line(...[...$points[$index], ...$end]);
        }
        $cuts = [...$cuts, ...$this->precisionArc($l / 2, -$w / 2, $diameter / 2, 0, 2 * M_PI)];
        $folds = [$this->line(0, 0, $l, 0), $this->line($l, 0, $l, -$w), $this->line($l, -$w, 0, -$w), $this->line(0, -$w, 0, 0)];

        return $this->panelGeometry([['cut' => $cuts, 'crease' => $folds]], [$this->label($l / 2, -$w * .8, 'Insert platform')]);
    }
}

<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsPrecisionGeometry;
use App\Services\Dielines\DielineTemplateContract;
use App\Services\Dielines\Geometry\PanelProfile;

class CarryBagTemplate implements DielineTemplateContract
{
    use BuildsPrecisionGeometry;

    public function key(): string
    {
        return 'carry-bag';
    }

    public function name(): string
    {
        return 'Cord Handle Bag';
    }

    public function description(): string
    {
        return 'A gusseted paper carry bag with a folded top reinforcement, eight matching cord holes and a pasted diamond bottom.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/9454/carton-bags-pillows-becf-12101';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_945420260912-2-tm0h9t.svg';
    }

    public function defaults(): array
    {
        return ['l' => 260, 'w' => 140, 'h' => 300, 'top_hem' => 40, 'bottom_depth' => 90, 'glue_flap' => 18, 'handle_span' => 100, 'handle_drop' => 15, 'hole_diameter' => 5];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'top_hem', 'label' => 'Top reinforcement depth', 'default' => 40, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'bottom_depth', 'label' => 'Bottom flap depth', 'default' => 38, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'glue_flap', 'label' => 'Side seam width', 'default' => 13, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'handle_span', 'label' => 'Cord hole spacing', 'default' => 50, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'handle_drop', 'label' => 'Cord hole drop', 'default' => 15, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'hole_diameter', 'label' => 'Cord hole diameter', 'default' => 5, 'min' => 1, 'suffix' => 'mm'],
        ];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['handle_span' => ['max:'.($v['l'] - 2 * $v['hole_diameter'])], 'handle_drop' => ['max:'.($v['top_hem'] - $v['hole_diameter'])], 'hole_diameter' => ['max:'.min($v['handle_drop'], $v['l'] / 5)], 'bottom_depth' => ['min:'.($v['w'] / 2), 'max:'.$v['w']], 'glue_flap' => ['max:'.($v['w'] / 2)]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h] = $v;
        $columns = [[-13, -$v['glue_flap']], [0, 0], [100, $l], [125, $l + $w / 2], [150, $l + $w], [250, 2 * $l + $w], [275, 2 * $l + 1.5 * $w], [298, 2 * ($l + $w) - 2]];
        $rows = [[-190, -$h - $v['top_hem']], [-150, -$h], [-25, -$w / 2], [0, 0], [25, $w / 2], [38, $v['bottom_depth']]];
        $layers = (new PanelProfile)->project($this->key(), fn (float $x, float $y): array => [PanelProfile::station($x, $columns), PanelProfile::station($y, $rows)], array_column($rows, 0), array_column($columns, 0));
        foreach ([$l / 2, 1.5 * $l + $w] as $centre) {
            foreach ([-1, 1] as $side) {
                foreach ([-1, 1] as $fold) {
                    $layers['cut'] = [...$layers['cut'], ...$this->precisionArc($centre + $side * $v['handle_span'] / 2, -$h + $fold * $v['handle_drop'], $v['hole_diameter'] / 2, 0, 2 * M_PI)];
                }
            }
        }

        return $this->panelGeometry([$layers], [$this->label($l / 2, -$h / 2, 'Front'), $this->label(1.5 * $l + $w, -$h / 2, 'Back')]);
    }
}

<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsPrecisionGeometry;
use App\Services\Dielines\DielineTemplateContract;
use App\Services\Dielines\Geometry\PanelProfile;

class GableBoxTemplate implements DielineTemplateContract
{
    use BuildsPrecisionGeometry;

    public function key(): string
    {
        return 'gable-box';
    }

    public function name(): string
    {
        return 'Handle Carry Box';
    }

    public function description(): string
    {
        return 'A carry carton with two circular grip openings, sloping top shoulders and an interlocking four-flap base.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/52202/special-boxes-becf-11e16';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_5220220260912-2-p39fka.svg';
    }

    public function defaults(): array
    {
        return ['l' => 160, 'w' => 90, 'h' => 100, 'handle_height' => 80, 'hole_diameter' => 28];
    }

    public function advancedFields(): array
    {
        return [['key' => 'handle_height', 'label' => 'Handle panel height', 'default' => 80, 'min' => 1, 'suffix' => 'mm'], ['key' => 'hole_diameter', 'label' => 'Grip hole diameter', 'default' => 22, 'min' => 1, 'suffix' => 'mm']];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['hole_diameter' => ['max:'.min($v['w'] * .7, $v['handle_height'] * .5)], 'w' => ['max:'.$v['l']]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'handle_height' => $handle] = $v;
        $columns = [[-13, -13], [0, 0], [50, $w], [101, $l], [126, $l + $w / 2], [151, $l + $w], [201, $l + 2 * $w], [252, 2 * $l + $w], [277, 2 * $l + 1.5 * $w], [301.5, 2 * ($l + $w) - .5]];
        $rows = [[-150, -$h - $handle], [-70, -$h], [-50, -$w], [0, 0], [25, $w / 2], [38, $w * .76]];
        $layers = (new PanelProfile)->project($this->key(), fn (float $x, float $y): array => [PanelProfile::station($x, $columns), PanelProfile::station($y, $rows)], array_column($rows, 0), array_column($columns, 0));
        foreach ([$w / 2, $l + 1.5 * $w] as $centre) {
            $layers['cut'] = [...$layers['cut'], ...$this->precisionArc($centre, -$h - $handle * .68125, $v['hole_diameter'] / 2, 0, 2 * M_PI)];
        }

        return $this->panelGeometry([$layers], [$this->label($l / 2, -$h / 2, 'Front'), $this->label(1.5 * $l + $w, -$h / 2, 'Back')]);
    }
}

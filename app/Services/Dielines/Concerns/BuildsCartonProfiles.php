<?php

namespace App\Services\Dielines\Concerns;

trait BuildsCartonProfiles
{
    use BuildsReferencedDielines;

    public function advancedFields(): array
    {
        return [
            ['key' => 'tuck_flap', 'label' => 'Tuck flap height', 'default' => 15, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'glue_flap', 'label' => 'Side seam width', 'default' => 13, 'min' => 1, 'suffix' => 'mm'],
        ];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['tuck_flap' => ['max:'.min($v['l'] * .4, $v['w'] * .8)], 'glue_flap' => ['max:'.($v['w'] / 2)]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'tuck_flap' => $tuck,'glue_flap' => $glue] = $v;
        $columns = [[-13, -$glue], [0, 0], [100, $l], [150, $l + $w], [250, 2 * $l + $w], [299.5, 2 * ($l + $w) - .5]];
        $rows = [[-214, -$h - $w + 1 - $tuck], [-199, -$h - $w + 1], [-150.5, -$h - .5], [-150, -$h], [0, 0]];
        $crash = $this->key() === 'auto-bottom-tuck-top';
        $locking = $crash || $this->key() === 'snap-lock-bottom-tuck-top';
        $rows = [...$rows, ...($locking ? [[25, $w / 2], [50, $w]] : [[.5, .5], [49, $w - 1], [64, $w - 1 + $tuck]])];
        $geometry = $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$h / 2, 'Front'), $this->label(1.5 * $l + $w, -$h / 2, 'Back')]);
        $inset = min($glue * .27, $h / 4);
        $geometry['layers']['glue'] = [['points' => [['x' => -$glue + .5, 'y' => -$h + $inset + .5], ['x' => -.5, 'y' => -$h + .5], ['x' => -.5, 'y' => -.5], ['x' => -$glue + .5, 'y' => -$inset - .5]]]];
        if ($crash) {
            foreach ([0, $l + $w] as $offset) {
                $geometry['layers']['glue'][] = ['points' => [['x' => $offset + $l - 1, 'y' => $w * .2], ['x' => $offset + $l - 1, 'y' => $w * .62], ['x' => $offset + $l - $w * .22, 'y' => $w * .5]]];
            }
        }

        return $geometry;
    }
}

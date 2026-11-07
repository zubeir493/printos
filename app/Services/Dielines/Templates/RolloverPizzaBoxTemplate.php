<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsReferencedDielines;
use App\Services\Dielines\DielineTemplateContract;

class RolloverPizzaBoxTemplate implements DielineTemplateContract
{
    use BuildsReferencedDielines;

    public function key(): string
    {
        return 'rollover-pizza-box';
    }

    public function name(): string
    {
        return 'Rollover Pizza Box';
    }

    public function description(): string
    {
        return 'A pizza carton with double locking sidewalls, three locking lugs per side, rounded lid wings and four semicircular corner locks.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/74945/tray-boxes-becf-21712';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_7494520260912-2-fkf2ts.svg';
    }

    public function defaults(): array
    {
        return ['l' => 320, 'w' => 320, 'h' => 40, 'board_thickness' => 1];
    }

    public function advancedFields(): array
    {
        return [['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 1, 'min' => .1, 'suffix' => 'mm']];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return ['board_thickness' => ['max:'.($v['h'] / 10)]];
    }

    public function generate(array $dimensions): array
    {
        $v = [...$this->defaults(), ...$dimensions];
        ['l' => $l,'w' => $w,'h' => $h,'board_thickness' => $t] = $v;
        $columns = [[-81.1, -$h - 3.6 * $t - 17.5], [-63.6, -$h - 3.6 * $t], [-60, -$h], [0, 0], [.9, .9 * $t], [1.8, 1.8 * $t], [3.6, 3.6 * $t], [196.4, $l - 3.6 * $t], [198.2, $l - 1.8 * $t], [199.1, $l - .9 * $t], [200, $l], [260, $l + $h], [263.6, $l + $h + 3.6 * $t], [281.1, $l + $h + 3.6 * $t + 17.5]];
        $rows = [[-440.9, -2 * $w - 2 * $h - .9 * $t], [-380.9, -2 * $w - $h - .9 * $t], [-220, -$w - $h], [-160, -$w], [0, 0], [60, $h]];

        return $this->referencedGeometry($this->key(), $columns, $rows, [$this->label($l / 2, -$w / 2, 'Pizza base'), $this->label($l / 2, -1.5 * $w - $h, 'Lid')]);
    }
}

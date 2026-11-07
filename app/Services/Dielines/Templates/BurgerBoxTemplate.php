<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsPackagingPanels;
use App\Services\Dielines\DielineTemplateContract;
use App\Services\Dielines\Geometry\PanelProfile;

class BurgerBoxTemplate implements DielineTemplateContract
{
    use BuildsPackagingPanels;

    public function key(): string
    {
        return 'burger-box';
    }

    public function name(): string
    {
        return 'Burger Box';
    }

    public function description(): string
    {
        return 'A tapered clamshell with rounded corner wings, a recessed front opening, folding hood, paired locking hooks, and a perforated hinge receiver.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/186592/food-boxes-becf-12019';
    }

    public function baseDimensionLabels(): array
    {
        return ['l' => 'Base length', 'w' => 'Base width', 'h' => 'Base wall height'];
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_18659220260912-2-neof7q.svg';
    }

    public function defaults(): array
    {
        return ['l' => 110, 'w' => 110, 'h' => 35, 'lid_depth' => 30, 'lid_panel_depth' => 100, 'hinge_depth' => 30, 'tuck_flap' => 29.5, 'board_thickness' => 0.5];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'lid_depth', 'label' => 'Lid wall height', 'default' => 50, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'lid_panel_depth', 'label' => 'Lid panel depth', 'default' => 89.133, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'hinge_depth', 'label' => 'Lid rear wall height', 'default' => 49.133, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'tuck_flap', 'label' => 'Front return depth', 'default' => 49.5, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 0.5, 'min' => 0.1, 'suffix' => 'mm'],
        ];
    }

    public function dimensionRules(array $dimensions): array
    {
        $values = $this->dimensionsForRules($dimensions);

        return [
            'lid_depth' => ['max:'.$values['h']],
            'tuck_flap' => ['max:'.$values['h']],
            'board_thickness' => ['max:'.min($values['h'] / 10, $values['lid_depth'] / 10)],
        ];
    }

    public function generate(array $dimensions): array
    {
        $values = [...$this->defaults(), ...$dimensions];
        $length = $this->dimension($values, 'l', 200);
        $width = $this->dimension($values, 'w', 100);
        $height = $this->dimension($values, 'h', 100);
        $lidHeight = $this->dimension($values, 'lid_depth', 50);
        $lidDepth = $this->dimension($values, 'lid_panel_depth', 89.133);
        $rearHeight = $this->dimension($values, 'hinge_depth', 49.133);
        $returnDepth = $this->dimension($values, 'tuck_flap', 49.5);
        $board = $this->dimension($values, 'board_thickness', 0.5);
        $hinge = -$width - $height - $board;
        $rear = $hinge - $rearHeight;
        $front = $rear - $lidDepth;
        $rim = $front - $lidHeight;
        $stations = [
            [-438.2653961417, $rim - $returnDepth], [-388.7653961417, $rim],
            [-338.7653961417, $front], [-249.6326980708, $rear],
            [-200.5, $hinge], [-100, -$width], [0, 0], [100, $height],
        ];
        $lidHalfWidth = $length / 2 + $height * tan(deg2rad(10)) + 3 * $board;
        $layers = (new PanelProfile)->project('burger-box', function (float $x, float $y) use ($stations, $length, $height, $lidHeight, $lidHalfWidth): array {
            $lidFraction = max(0, min(1, (-$y - 200) / 2));
            $sourceHalfWidth = 100 + $lidFraction * 19.1326980708;
            $targetHalfWidth = $length / 2 + $lidFraction * ($lidHalfWidth - $length / 2);
            $offset = $x - 100;
            $distance = abs($offset);
            $wingScale = (1 - $lidFraction) * $height / 100 + $lidFraction * $lidHeight / 50;
            $projected = $distance <= $sourceHalfWidth
                ? $distance * $targetHalfWidth / $sourceHalfWidth
                : $targetHalfWidth + ($distance - $sourceHalfWidth) * $wingScale;

            return [$length / 2 + ($offset < 0 ? -$projected : $projected), PanelProfile::station($y, $stations)];
        }, [...array_column($stations, 0), -200, -202]);

        return $this->panelGeometry([$layers], [
            $this->label($length / 2, -$width / 2, 'Burger base'),
            $this->label($length / 2, ($rear + $front) / 2, 'Lid'),
        ]);
    }
}

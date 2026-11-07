<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsPackagingPanels;
use App\Services\Dielines\DielineTemplateContract;

class OpenFoodTrayTemplate implements DielineTemplateContract
{
    use BuildsPackagingPanels;

    public function key(): string
    {
        return 'open-food-tray';
    }

    public function name(): string
    {
        return 'Open Food Tray';
    }

    public function description(): string
    {
        return 'A single-wall serving tray with four bevelled corner glue tabs, caliper relief at each corner, and an open top.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://www.diecuttemplates.com/dielines/9314/tray-boxes-becf-11a07';
    }

    public function referenceDrawingUrl(): ?string
    {
        return 'https://d2atdwxjx7uc4i.cloudfront.net/svg/s_931420260912-2-cfp3s7.svg';
    }

    public function defaults(): array
    {
        return ['l' => 180, 'w' => 120, 'h' => 40, 'corner_tab' => 15, 'board_thickness' => 0.5];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'corner_tab', 'label' => 'Corner glue-tab width', 'default' => 15, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 0.5, 'min' => 0.1, 'suffix' => 'mm'],
        ];
    }

    public function dimensionRules(array $dimensions): array
    {
        $v = $this->dimensionsForRules($dimensions);

        return [
            'corner_tab' => ['max:'.min($v['w'] / 2, $v['h'])],
            'board_thickness' => ['max:'.min($v['l'], $v['w'], $v['h']) / 10],
        ];
    }

    public function generate(array $dimensions): array
    {
        $length = $this->dimension($dimensions, 'l', 200);
        $width = $this->dimension($dimensions, 'w', 100);
        $height = $this->dimension($dimensions, 'h', 40);
        $board = min($this->dimension($dimensions, 'board_thickness', 0.5), min($length, $width, $height) / 10);
        $tab = min($this->dimension($dimensions, 'corner_tab', 15), $width / 2, $height);
        $bevel = $tab * tan(deg2rad(15));
        $cut = [];
        $crease = [];
        $glue = [];

        foreach ([0.0, -$width] as $root) {
            $sign = $root === 0.0 ? 1 : -1;
            $tip = $root + $sign * $height;
            $cut[] = $this->line($board, $tip, $length - $board, $tip);
            $crease[] = $this->line($length - $board, $root, $board, $root);

            foreach ([[$board, -1], [$length - $board, 1]] as [$x, $outward]) {
                $free = $x + $outward * $tab;
                $cut = [...$cut,
                    $this->line($free, $root + $sign * $bevel, $x, $root),
                    $this->line($x, $tip, $free, $tip - $sign * $bevel),
                    $this->line($free, $tip - $sign * $bevel, $free, $root + $sign * $bevel),
                ];
                $crease[] = $this->line($x, $tip, $x, $root);
                $inset = min(2, $tab / 4, ($height - 2 * $bevel) / 4);
                $glue[] = $this->glueArea(min($x, $free) + $inset, min($root, $tip) + $bevel + $inset, $tab - 2 * $inset, $height - 2 * ($bevel + $inset));
            }
        }

        foreach ([[0.0, -1], [$length, 1]] as [$x, $sign]) {
            $free = $x + $sign * $height;
            $crease[] = $this->line($x, -$width + 0.1, $x, -0.1);
            $cut[] = $this->line($free, -$board, $free, -$width + $board);

            foreach ([[-$board, 0.0], [-$width + $board, -$width]] as [$edge, $root]) {
                $relief = $x + $sign * 4 * $board;
                $corner = $x - $sign * $board;
                $cut[] = $this->line($relief, $edge, $corner, $root);
                $cut[] = $this->line($free, $edge, $relief, $edge);
            }
        }

        return $this->panelGeometry([['cut' => $cut, 'crease' => $crease, 'glue' => $glue]], [$this->label($length / 2, -$width / 2, 'Tray base')]);
    }
}

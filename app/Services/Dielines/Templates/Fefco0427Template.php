<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;

class Fefco0427Template implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    public function key(): string
    {
        return 'fefco-0427';
    }

    public function name(): string
    {
        return 'FEFCO 0427';
    }

    public function description(): string
    {
        return 'One-piece folder-style tray with hinged lid and self-locking side walls.';
    }

    public function defaults(): array
    {
        return [
            'l' => 220,
            'w' => 160,
            'h' => 45,
            'lid_tuck' => 35,
            'side_lock' => 28,
            'front_lock' => 24,
            'bleed' => 3,
            'board_thickness' => 1.5,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'lid_tuck', 'label' => 'Lid tuck', 'default' => 35, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'side_lock', 'label' => 'Side lock tabs', 'default' => 28, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'front_lock', 'label' => 'Front lock tabs', 'default' => 24, 'min' => 1, 'suffix' => 'mm'],
            ['key' => 'bleed', 'label' => 'Bleed', 'default' => 3, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 1.5, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    public function generate(array $dimensions): array
    {
        $defaults = $this->defaults();
        $length = $this->dimension($dimensions, 'l', $defaults['l']);
        $width = $this->dimension($dimensions, 'w', $defaults['w']);
        $height = $this->dimension($dimensions, 'h', $defaults['h']);
        $lidTuck = $this->dimension($dimensions, 'lid_tuck', $defaults['lid_tuck']);
        $sideLock = $this->dimension($dimensions, 'side_lock', $defaults['side_lock']);
        $frontLock = $this->dimension($dimensions, 'front_lock', $defaults['front_lock']);
        $bleed = $this->dimension($dimensions, 'bleed', $defaults['bleed']);

        $left = $sideLock + $height;
        $right = $left + $length;
        $baseTop = $height + $lidTuck + $width + $height;
        $baseBottom = $baseTop + $width;
        $totalWidth = $length + ($height * 2) + ($sideLock * 2);
        $totalHeight = $lidTuck + ($width * 2) + ($height * 3) + $frontLock;

        $cut = [
            $this->line($left, 0, $right, 0),
            $this->line($right, 0, $right, $lidTuck),
            $this->line($right + $height, $lidTuck, $right + $height, $baseBottom + $height),
            $this->line($right + $height + $sideLock, $baseTop, $right + $height + $sideLock, $baseBottom),
            $this->line($right, $baseBottom + $height + $frontLock, $left, $baseBottom + $height + $frontLock),
            $this->line($left - $height - $sideLock, $baseBottom, $left - $height - $sideLock, $baseTop),
            $this->line($left - $height, $baseBottom + $height, $left - $height, $lidTuck),
            $this->line($left, $lidTuck, $left, 0),
        ];

        $crease = [
            $this->line($left, $lidTuck, $right, $lidTuck),
            $this->line($left, $lidTuck + $width, $right, $lidTuck + $width),
            $this->line($left, $baseTop - $height, $right, $baseTop - $height),
            $this->line($left, $baseTop, $right, $baseTop),
            $this->line($left, $baseBottom, $right, $baseBottom),
            $this->line($left, $baseBottom + $height, $right, $baseBottom + $height),
            $this->line($left, $baseTop, $left, $baseBottom),
            $this->line($right, $baseTop, $right, $baseBottom),
            $this->line($left - $height, $baseTop, $left - $height, $baseBottom),
            $this->line($right + $height, $baseTop, $right + $height, $baseBottom),
        ];

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => ['width' => $totalWidth, 'height' => $totalHeight],
            'layers' => [
                'cut' => $cut,
                'crease' => $crease,
                'glue' => [],
                'bleed' => $this->bleedBox($left, $baseTop, $length, $width, $bleed),
            ],
            'labels' => [
                $this->label($left + ($length / 2), $baseTop + ($width / 2), 'Base'),
                $this->label($left + ($length / 2), $lidTuck + ($width / 2), 'Lid'),
                $this->label($left + ($length / 2), $baseTop - ($height / 2), 'Back wall'),
                $this->label($left + ($length / 2), $baseBottom + ($height / 2), 'Front wall'),
            ],
        ];
    }
}

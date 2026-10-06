<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;

class SnapLockBottomTemplate implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    public function key(): string
    {
        return 'snap-lock-bottom-tuck-top';
    }

    public function name(): string
    {
        return 'Snap-Lock Bottom, Tuck-Top Carton';
    }

    public function description(): string
    {
        return 'A long-seam-glued carton with a manually assembled 1-2-3 snap-lock base and a tuck-top closure (ECMA A55.20 family).';
    }

    public function defaults(): array
    {
        return [
            'l' => 60,
            'w' => 40,
            'h' => 100,
            'tuck_flap' => 18,
            'tuck_radius' => 5,
            'dust_flap' => 20,
            'glue_flap' => 12,
            'lock_tab' => 5,
            'board_thickness' => 0.45,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'tuck_flap', 'label' => 'Tuck flap height', 'default' => 18, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'tuck_radius', 'label' => 'Tuck radius', 'default' => 5, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'dust_flap', 'label' => 'Dust flap depth', 'default' => 20, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'glue_flap', 'label' => 'Glue flap width', 'default' => 12, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'lock_tab', 'label' => 'Bottom lock tab depth', 'default' => 5, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'board_thickness', 'label' => 'Board thickness', 'default' => 0.45, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    public function generate(array $dimensions): array
    {
        $defaults = $this->defaults();
        $length = $this->dimension($dimensions, 'l', $defaults['l']);
        $width = $this->dimension($dimensions, 'w', $defaults['w']);
        $height = $this->dimension($dimensions, 'h', $defaults['h']);
        $tuckHeight = $this->dimension($dimensions, 'tuck_flap', $defaults['tuck_flap']);
        $radius = $this->dimension($dimensions, 'tuck_radius', $defaults['tuck_radius']);
        $dustDepth = $this->dimension($dimensions, 'dust_flap', $defaults['dust_flap']);
        $glueWidth = $this->dimension($dimensions, 'glue_flap', $defaults['glue_flap']);
        $boardThickness = $this->dimension($dimensions, 'board_thickness', $defaults['board_thickness']);
        $lockTabDepth = min($this->dimension($dimensions, 'lock_tab', $defaults['lock_tab']), $width * 0.15);
        $tongueWidth = min($length * 0.32, max(0, $length - ($boardThickness * 2)));

        $bodyX = $glueWidth;
        $firstWidthX = $bodyX + $length;
        $secondLengthX = $firstWidthX + $width;
        $secondWidthX = $secondLengthX + $length;
        $bodyRight = $secondWidthX + $width;
        $topClosureY = -$width - 1;
        $closureHeight = $width;
        $topClosure = $this->tuckFlapComponent(
            $bodyX,
            $topClosureY,
            $length,
            $closureHeight,
            'top',
            $tuckHeight,
            $radius,
        );
        $topDustFlaps = [
            $this->dustFlap($firstWidthX, 0, $width, $dustDepth, 'top'),
            $this->dustFlap($secondWidthX, 0, $width, $dustDepth, 'top'),
        ];
        $mainBottomDepth = $width * 0.5;
        $lockingBottom = $this->closurePanel($bodyX, $height, $length, $mainBottomDepth, 'bottom', $tongueWidth, $lockTabDepth);
        $receiverBottom = $this->closurePanel($secondLengthX, $height, $length, $mainBottomDepth, 'bottom');
        $sideBottomDepth = $length * 0.5;
        $sideFlaps = [
            $this->dustFlap($firstWidthX, $height, $width, $sideBottomDepth, 'bottom'),
            $this->dustFlap($secondWidthX, $height, $width, $sideBottomDepth, 'bottom'),
        ];
        $slotClearance = $boardThickness * 2;
        $bottomSlot = $this->lockingSlot(
            $secondLengthX,
            $height,
            $length,
            max(0, $mainBottomDepth - max($boardThickness, 0.5)),
            min($length, $tongueWidth + $slotClearance),
            'bottom',
        );
        $glueFlap = $this->glueFlapComponent($bodyX, 0, $glueWidth, $height, 'left');

        $cut = [
            $this->line($secondLengthX, 0, $secondWidthX, 0),
            $this->line($bodyRight, 0, $bodyRight, $height),
            ...$topClosure['cut'],
            ...$lockingBottom['cut'],
            ...$receiverBottom['cut'],
            $bottomSlot,
            ...$glueFlap['cut'],
        ];
        $crease = [
            $this->line($bodyX + $length, 0, $bodyX + $length, $height),
            $this->line($secondLengthX, 0, $secondLengthX, $height),
            $this->line($secondWidthX, 0, $secondWidthX, $height),
            ...$topClosure['crease'],
            ...$lockingBottom['crease'],
            ...$receiverBottom['crease'],
            ...$glueFlap['crease'],
        ];

        foreach ([...$topDustFlaps, ...$sideFlaps] as $sideFlap) {
            $cut = [...$cut, ...$sideFlap['cut']];
            $crease = [...$crease, ...$sideFlap['crease']];
        }

        $bottomDepth = max($mainBottomDepth + $lockTabDepth, $sideBottomDepth);
        $minimumY = min($topClosureY - $tuckHeight + 1, -$dustDepth);

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => [
                'x' => 0.0,
                'y' => $minimumY,
                'width' => $bodyRight,
                'height' => $height + $bottomDepth - $minimumY,
            ],
            'layers' => [
                'cut' => $cut,
                'crease' => $crease,
                'glue' => $glueFlap['glue'],
                'bleed' => [],
            ],
            'labels' => [
                $this->label($bodyX + ($length / 2), $height / 2, 'Length'),
                $this->label($firstWidthX + ($width / 2), $height / 2, 'Width'),
                $this->label($secondLengthX + ($length / 2), $height / 2, 'Length'),
                $this->label($secondWidthX + ($width / 2), $height / 2, 'Width'),
            ],
        ];
    }
}

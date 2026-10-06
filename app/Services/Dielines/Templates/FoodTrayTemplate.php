<?php

namespace App\Services\Dielines\Templates;

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateContract;
use App\Services\Dielines\Geometry\DielinePath;

class FoodTrayTemplate implements DielineTemplateContract
{
    use BuildsDielineGeometry;

    public function key(): string
    {
        return 'four-corner-food-tray';
    }

    public function name(): string
    {
        return 'Four-Corner Glued Food Tray with Tuck Lid';
    }

    public function description(): string
    {
        return 'A one-piece four-corner glued food tray with a hinged lid, shaped side panels, and a centered tuck tab.';
    }

    public function defaults(): array
    {
        return [
            'l' => 150,
            'w' => 100,
            'h' => 50,
            'corner_tab' => 52,
            'tuck_flap' => 19.5,
            'tuck_tab_width' => 50,
            'corner_radius' => 6,
            'bottom_corner_radius' => 10,
            'lock_notch' => 3,
        ];
    }

    public function advancedFields(): array
    {
        return [
            ['key' => 'corner_tab', 'label' => 'Side glue-tab projection', 'default' => 52, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'tuck_flap', 'label' => 'Tuck-tab depth', 'default' => 19.5, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'tuck_tab_width', 'label' => 'Tuck-tab width', 'default' => 50, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'corner_radius', 'label' => 'Lid-panel corner radius', 'default' => 6, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'bottom_corner_radius', 'label' => 'Bottom-wall corner radius', 'default' => 10, 'min' => 0, 'suffix' => 'mm'],
            ['key' => 'lock_notch', 'label' => 'Glue-tab notch inset', 'default' => 3, 'min' => 0, 'suffix' => 'mm'],
        ];
    }

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string, mixed>
     */
    public function generate(array $dimensions): array
    {
        $defaults = $this->defaults();
        $length = $this->dimension($dimensions, 'l', $defaults['l']);
        $width = $this->dimension($dimensions, 'w', $defaults['w']);
        $wallHeight = $this->dimension($dimensions, 'h', $defaults['h']);
        $sideGlueProjection = $this->dimension($dimensions, 'corner_tab', $defaults['corner_tab']);
        $tuckDepth = $this->dimension($dimensions, 'tuck_flap', $defaults['tuck_flap']);
        $tuckTabWidth = min($this->dimension($dimensions, 'tuck_tab_width', $defaults['tuck_tab_width']), $length);
        $cornerRadius = $this->dimension($dimensions, 'corner_radius', $defaults['corner_radius']);
        $bottomCornerRadius = $this->dimension($dimensions, 'bottom_corner_radius', $defaults['bottom_corner_radius']);
        $notchInset = min($this->dimension($dimensions, 'lock_notch', $defaults['lock_notch']), $sideGlueProjection);

        $leftGlueOuter = 0.0;
        $leftGlueRoot = $sideGlueProjection;
        $baseLeft = $leftGlueRoot + $wallHeight;
        $baseRight = $baseLeft + $length;
        $rightGlueRoot = $baseRight + $wallHeight;
        $rightGlueOuter = $rightGlueRoot + $sideGlueProjection;

        $frontWallTop = $tuckDepth;
        $frontWallBottom = $frontWallTop + $wallHeight;
        $lidPanelEnd = $frontWallBottom + $width;
        $backWallEnd = $lidPanelEnd + $wallHeight;
        $baseStart = $backWallEnd;
        $baseEnd = $backWallEnd + $width;
        $bottomWallEnd = $baseEnd + $wallHeight;

        $tuckTabLeft = $baseLeft + (($length - $tuckTabWidth) / 2);
        $tuckTabRight = $tuckTabLeft + $tuckTabWidth;
        $lidSideRadius = min($cornerRadius, $wallHeight / 2);
        $sideShoulder = min($wallHeight * 0.3, $width * 0.2);
        $sideCornerRadius = min($cornerRadius, $wallHeight / 3, $sideShoulder);
        $longLidSideProjection = max(0, $wallHeight - 2);
        $bottomWallLeft = $leftGlueRoot + ($wallHeight - $longLidSideProjection);
        $bottomWallWidth = $length + ($longLidSideProjection * 2);
        $notchHeight = min($width * 0.4, max($wallHeight * 0.8, $width * 0.25));
        $notchStart = $baseStart + (($width - $notchHeight) / 2);
        $notchEnd = $notchStart + $notchHeight;

        $tuckTab = $this->roundedFlapComponent($tuckTabLeft, $frontWallTop, $tuckTabWidth, $tuckDepth, 'top', min($cornerRadius, $tuckDepth, $tuckTabWidth / 2));
        $bottomWall = $this->roundedFlapComponent($bottomWallLeft, $baseEnd, $bottomWallWidth, $wallHeight, 'bottom', min($bottomCornerRadius, $wallHeight, $bottomWallWidth / 2));
        $frontLidSideProjection = max(0, $wallHeight - $lidSideRadius);
        $topLidSideLeft = $this->frontLidSidePanel($baseLeft, $frontWallTop, $wallHeight, $frontLidSideProjection, 'left', $lidSideRadius);
        $topLidSideRight = $this->frontLidSidePanel($baseRight, $frontWallTop, $wallHeight, $frontLidSideProjection, 'right', $lidSideRadius);
        $longLidSideLeft = $this->profiledLidSidePanel($baseLeft, $frontWallBottom, $width, $longLidSideProjection, 'left', $sideShoulder, $sideCornerRadius);
        $longLidSideRight = $this->profiledLidSidePanel($baseRight, $frontWallBottom, $width, $longLidSideProjection, 'right', $sideShoulder, $sideCornerRadius);
        $leftGlueTab = $this->notchedSideGlueTab($leftGlueRoot, $baseStart, $width, $sideGlueProjection, 'left', $notchStart - $baseStart, $notchEnd - $baseStart, $notchInset);
        $rightGlueTab = $this->notchedSideGlueTab($rightGlueRoot, $baseStart, $width, $sideGlueProjection, 'right', $notchStart - $baseStart, $notchEnd - $baseStart, $notchInset);

        $cuts = [
            $this->line($baseLeft, $frontWallTop, $tuckTabLeft, $frontWallTop),
            $this->line($tuckTabRight, $frontWallTop, $baseRight, $frontWallTop),
            ...$tuckTab['cut'],

            ...$topLidSideLeft['cut'],
            ...$topLidSideRight['cut'],
            ...$longLidSideLeft['cut'],
            ...$longLidSideRight['cut'],

            $this->line($baseLeft, $lidPanelEnd, $baseLeft, $backWallEnd),
            $this->line($baseRight, $lidPanelEnd, $baseRight, $backWallEnd),
            $this->line($baseLeft, $backWallEnd, $leftGlueRoot, $backWallEnd),
            $this->line($rightGlueRoot, $backWallEnd, $baseRight, $backWallEnd),
            $this->line($baseLeft, $baseEnd, $bottomWallLeft, $baseEnd),
            $this->line($rightGlueRoot - ($wallHeight - $longLidSideProjection), $baseEnd, $baseRight, $baseEnd),
            ...$bottomWall['cut'],

            ...$leftGlueTab['cut'],
            ...$rightGlueTab['cut'],
        ];

        $glueInset = min(4, $sideGlueProjection / 4, $wallHeight / 4);
        $topGlueHeight = max(0, ($notchStart - $baseStart) - ($glueInset * 2));
        $glue = [];

        if ($topGlueHeight > 0 && $sideGlueProjection > ($glueInset * 2)) {
            $glue[] = $this->trapezoidGlueArea($leftGlueOuter + $glueInset, $baseStart + $glueInset, $leftGlueRoot - $glueInset, $baseStart + $glueInset + $topGlueHeight, $glueInset);
            $glue[] = $this->trapezoidGlueArea($rightGlueRoot + $glueInset, $baseStart + $glueInset, $rightGlueOuter - $glueInset, $baseStart + $glueInset + $topGlueHeight, $glueInset);
            $glue[] = $this->trapezoidGlueArea($leftGlueOuter + $glueInset, $notchEnd + $glueInset, $leftGlueRoot - $glueInset, $baseEnd - $glueInset, $glueInset);
            $glue[] = $this->trapezoidGlueArea($rightGlueRoot + $glueInset, $notchEnd + $glueInset, $rightGlueOuter - $glueInset, $baseEnd - $glueInset, $glueInset);
        }

        return [
            'template' => $this->key(),
            'name' => $this->name(),
            'unit' => 'mm',
            'bounds' => [
                'x' => $leftGlueOuter,
                'y' => 0.0,
                'width' => $rightGlueOuter - $leftGlueOuter,
                'height' => $bottomWallEnd,
            ],
            'layers' => [
                'cut' => $cuts,
                'crease' => [
                    $this->line($baseLeft, $lidPanelEnd, $baseRight, $lidPanelEnd),
                    $this->line($baseLeft, $backWallEnd, $baseRight, $backWallEnd),
                    $this->line($baseLeft, $baseEnd, $baseRight, $baseEnd),
                    $this->line($baseLeft, $backWallEnd, $baseLeft, $baseEnd),
                    $this->line($baseRight, $backWallEnd, $baseRight, $baseEnd),
                    $this->line($baseLeft, $baseEnd, $baseLeft, $bottomWallEnd),
                    $this->line($baseRight, $baseEnd, $baseRight, $bottomWallEnd),
                    ...$topLidSideLeft['crease'],
                    ...$topLidSideRight['crease'],
                    ...$longLidSideLeft['crease'],
                    ...$longLidSideRight['crease'],
                    ...$leftGlueTab['crease'],
                    ...$rightGlueTab['crease'],
                    ...$tuckTab['crease'],
                    $this->line($baseLeft, $frontWallBottom, $baseRight, $frontWallBottom),
                ],
                'glue' => $glue,
                'bleed' => [],
            ],
            'labels' => [
                $this->label($baseLeft + ($length / 2), $backWallEnd + ($width / 2), 'Tray base'),
                $this->label($baseLeft + ($length / 2), $lidPanelEnd + ($wallHeight / 2), 'Long wall'),
                $this->label(($leftGlueRoot + $baseLeft) / 2, $backWallEnd + ($width / 2), 'Side wall'),
                $this->label($baseLeft + ($length / 2), $frontWallBottom + ($width / 2), 'Lid panel'),
                $this->label($baseLeft + ($length / 2), $frontWallTop + ($wallHeight / 2), 'Front wall'),
                $this->label(($tuckTabLeft + $tuckTabRight) / 2, $frontWallTop - ($tuckDepth / 2), 'Tuck tab'),
            ],
        ];
    }

    /**
     * Build the curved side panel attached to the front wall of the lid.
     *
     * @return array{cut: array<int, array{x1: float, y1: float, x2: float, y2: float}>, crease: array<int, array{x1: float, y1: float, x2: float, y2: float}>}
     */
    private function frontLidSidePanel(float $x, float $y, float $span, float $depth, string $direction, float $radius): array
    {
        $span = max(0, $span);
        $depth = max(0, $depth);
        $radius = min(max(0, $radius), $span, $depth);
        $curveEnd = $span - $radius;
        $cut = $this->drawPath(
            $x,
            $y,
            $direction,
            function (DielinePath $path) use ($span, $depth, $radius, $curveEnd): void {
                $path->moveTo(0, 0);

                for ($segment = 1; $segment <= 12; $segment++) {
                    $progress = $segment / 12;
                    $remaining = 1 - $progress;
                    $localX = ($progress ** 2) * $curveEnd;
                    $localY = -$depth * ((2 * $remaining * $progress) + ($progress ** 2));

                    $path->lineTo($localX, $localY);
                }

                $path->arcTo($span, -$depth, $span, 0, $radius)
                    ->lineTo($span, 0);
            },
        );

        return [
            'cut' => $cut,
            'crease' => [$this->line($x, $y, $x, $y + $span)],
        ];
    }

    /**
     * Build a shaped lid-side panel with eased transitions into its outer edge.
     *
     * @return array{cut: array<int, array{x1: float, y1: float, x2: float, y2: float}>, crease: array<int, array{x1: float, y1: float, x2: float, y2: float}>}
     */
    private function profiledLidSidePanel(float $x, float $y, float $span, float $depth, string $direction, float $shoulder, float $radius): array
    {
        $span = max(0, $span);
        $depth = max(0, $depth);
        $shoulder = min(max(0, $shoulder), $span / 2);
        $radius = min(max(0, $radius), $depth / 2, $shoulder, ($span - (2 * $shoulder)) / 4);
        $curveRun = $radius * 2;
        $cornerInset = min($radius, $depth);
        $topStart = [$shoulder, -$depth + $cornerInset];
        $topEnd = [$shoulder + $curveRun, -$depth];
        $bottomStart = [$span - $shoulder - $curveRun, -$depth];
        $bottomEnd = [$span - $shoulder, -$depth + $cornerInset];
        $diagonalLength = sqrt(($shoulder ** 2) + (($depth - $cornerInset) ** 2));
        $diagonalTangentX = $diagonalLength > 0 ? ($radius * $shoulder) / $diagonalLength : 0;
        $diagonalTangentY = $diagonalLength > 0 ? ($radius * ($depth - $cornerInset)) / $diagonalLength : 0;
        $appendCubic = static function (DielinePath $path, array $start, array $firstControl, array $secondControl, array $end): void {
            for ($segment = 1; $segment <= 16; $segment++) {
                $progress = $segment / 16;
                $remaining = 1 - $progress;
                $path->lineTo(
                    ($remaining ** 3 * $start[0]) + (3 * $remaining ** 2 * $progress * $firstControl[0]) + (3 * $remaining * $progress ** 2 * $secondControl[0]) + ($progress ** 3 * $end[0]),
                    ($remaining ** 3 * $start[1]) + (3 * $remaining ** 2 * $progress * $firstControl[1]) + (3 * $remaining * $progress ** 2 * $secondControl[1]) + ($progress ** 3 * $end[1]),
                );
            }
        };
        $cut = $this->drawPath(
            $x,
            $y,
            $direction,
            function (DielinePath $path) use ($span, $depth, $curveRun, $topStart, $topEnd, $bottomStart, $bottomEnd, $diagonalTangentX, $diagonalTangentY, $appendCubic): void {
                $path->moveTo(0, 0)->lineTo($topStart[0], $topStart[1]);

                $appendCubic(
                    $path,
                    $topStart,
                    [$topStart[0] + $diagonalTangentX, $topStart[1] - $diagonalTangentY],
                    [$topEnd[0] - ($curveRun / 3), -$depth],
                    $topEnd,
                );

                $path->lineTo($bottomStart[0], $bottomStart[1]);

                $appendCubic(
                    $path,
                    $bottomStart,
                    [$bottomStart[0] + ($curveRun / 3), -$depth],
                    [$bottomEnd[0] - $diagonalTangentX, $bottomEnd[1] - $diagonalTangentY],
                    $bottomEnd,
                );

                $path->lineTo($span, 0);
            },
        );

        return [
            'cut' => $cut,
            'crease' => [
                $this->line($x, $y, $x, $y + $span),
            ],
        ];
    }

    /**
     * Build a full-height side glue tab with a centered locking notch.
     *
     * @return array{cut: array<int, array{x1: float, y1: float, x2: float, y2: float}>, crease: array<int, array{x1: float, y1: float, x2: float, y2: float}>}
     */
    private function notchedSideGlueTab(
        float $x,
        float $y,
        float $span,
        float $depth,
        string $direction,
        float $notchStart,
        float $notchEnd,
        float $notchInset,
    ): array {
        $span = max(0, $span);
        $depth = max(0, $depth);
        $notchStart = min(max(0, $notchStart), $span);
        $notchEnd = min(max($notchStart, $notchEnd), $span);
        $notchInset = min(max(0, $notchInset), $depth);
        $cut = $this->drawPath(
            $x,
            $y,
            $direction,
            function (DielinePath $path) use ($span, $depth, $notchStart, $notchEnd, $notchInset): void {
                $path->moveTo(0, 0)
                    ->lineTo(0, -$depth)
                    ->lineTo($notchStart, -$depth)
                    ->lineTo($notchStart, -$depth + $notchInset)
                    ->lineTo($notchEnd, -$depth + $notchInset)
                    ->lineTo($notchEnd, -$depth)
                    ->lineTo($span, -$depth)
                    ->lineTo($span, 0);
            },
        );

        return [
            'cut' => $cut,
            'crease' => [$this->line($x, $y, $x, $y + $span)],
        ];
    }

    /**
     * @return array{points: array<int, array{x: float, y: float}>}
     */
    private function trapezoidGlueArea(float $x1, float $y1, float $x2, float $y2, float $inset): array
    {
        return ['points' => [
            ['x' => $x1 + $inset, 'y' => $y1],
            ['x' => $x2 - $inset, 'y' => $y1],
            ['x' => $x2, 'y' => $y2],
            ['x' => $x1, 'y' => $y2],
        ]];
    }
}

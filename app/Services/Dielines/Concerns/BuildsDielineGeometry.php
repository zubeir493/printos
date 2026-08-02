<?php

namespace App\Services\Dielines\Concerns;

use App\Services\Dielines\Geometry\DielinePath;
use InvalidArgumentException;

trait BuildsDielineGeometry
{
    /**
     * @param  array<string, mixed>  $dimensions
     */
    protected function dimension(array $dimensions, string $key, float $default): float
    {
        return max(0, (float) ($dimensions[$key] ?? $default));
    }

    /**
     * @return array{x1: float, y1: float, x2: float, y2: float}
     */
    protected function line(float $x1, float $y1, float $x2, float $y2): array
    {
        return compact('x1', 'y1', 'x2', 'y2');
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function drawPath(float $x, float $y, string $direction, callable $draw, int $arcSegments = 8, ?float $mirrorWidth = null): array
    {
        $path = new DielinePath($x, $y, $direction, $arcSegments, $mirrorWidth);
        $draw($path);

        return $path->lines();
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function rectangle(float $x, float $y, float $width, float $height): array
    {
        return [
            $this->line($x, $y, $x + $width, $y),
            $this->line($x + $width, $y, $x + $width, $y + $height),
            $this->line($x + $width, $y + $height, $x, $y + $height),
            $this->line($x, $y + $height, $x, $y),
        ];
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function straightFlap(float $x, float $y, float $span, float $depth, string $direction): array
    {
        return $this->flap($x, $y, $span, $depth, $direction);
    }

    /**
     * Build a closure panel with a short rounded tuck flap above its crease.
     *
     * The local origin is the top of the closure panel. The closure extends
     * toward positive local Y; the tuck flap extends toward negative local Y.
     *
     * @return array{cut: array<int, array{x1: float, y1: float, x2: float, y2: float}>, crease: array<int, array{x1: float, y1: float, x2: float, y2: float}>}
     */
    protected function tuckFlapComponent(
        float $x,
        float $y,
        float $span,
        float $closureHeight,
        string $direction,
        float $tuckHeight = 15,
        float $radius = 6,
        float $slitWidth = 5,
        float $slitHeight = 2,
        int $arcSegments = 8,
    ): array {
        $span = max(0, $span);
        $closureHeight = max(0, $closureHeight);
        $tuckHeight = max(0, $tuckHeight);
        $radius = min(max(0, $radius), $span / 2, $tuckHeight);
        $slitWidth = min(max(0, $slitWidth), $span / 2);
        $slitHeight = min(max(0, $slitHeight), $closureHeight);
        $creaseOffset = $slitHeight / 2;
        $closureBottom = $creaseOffset + $closureHeight;
        $arcSegments = max(2, $arcSegments);

        $cut = $this->drawPath(
            $x,
            $y,
            $direction,
            function (DielinePath $ctx) use ($span, $closureBottom, $tuckHeight, $radius, $slitWidth, $slitHeight): void {
                $ctx->moveTo(0, 0)
                    ->lineTo(0, $closureBottom);

                $ctx->moveTo($span, 0)
                    ->lineTo($span, $closureBottom);

                $ctx->moveTo(0, 0)
                    ->lineTo(0, -$tuckHeight + $radius)
                    ->arcTo(0, -$tuckHeight, $radius, -$tuckHeight, $radius)
                    ->lineTo($span - $radius, -$tuckHeight)
                    ->arcTo($span, -$tuckHeight, $span, -$tuckHeight + $radius, $radius)
                    ->lineTo($span, 0);

                $ctx->moveTo(0, 0)
                    ->lineTo($slitWidth, 0)
                    ->lineTo($slitWidth, $slitHeight);

                $ctx->moveTo($span, 0)
                    ->lineTo($span - $slitWidth, 0)
                    ->lineTo($span - $slitWidth, $slitHeight);
            },
            $arcSegments,
        );

        return [
            'cut' => $cut,
            'crease' => [
                ...$this->drawPath(
                    $x,
                    $y,
                    $direction,
                    fn (DielinePath $ctx): DielinePath => $ctx
                        ->moveTo($slitWidth, $creaseOffset)
                        ->lineTo($span - $slitWidth, $creaseOffset),
                ),
                ...$this->drawPath(
                    $x,
                    $y,
                    $direction,
                    fn (DielinePath $ctx): DielinePath => $ctx
                        ->moveTo($span, $closureBottom)
                        ->lineTo(0, $closureBottom),
                ),
            ],
        ];
    }

    /**
     * Build a tapered side glue flap. The hinge edge is a crease and the
     * remaining perimeter is cut; the enclosed polygon is the glue area.
     *
     * @return array{cut: array<int, array{x1: float, y1: float, x2: float, y2: float}>, crease: array<int, array{x1: float, y1: float, x2: float, y2: float}>, glue: array<int, array{points: array<int, array{x: float, y: float}>}>}
     */
    protected function glueFlapComponent(
        float $x,
        float $y,
        float $width,
        float $height,
        string $direction,
        float $topTaper = 10,
        float $bottomTaper = 10,
    ): array {
        $width = max(0, $width);
        $height = max(0, $height);
        $topTaper = $this->clamp($topTaper, 0, $height);
        $bottomTaper = $this->clamp($bottomTaper, 0, $height - $topTaper);

        $points = match ($direction) {
            'left' => [
                ['x' => $x, 'y' => $y],
                ['x' => $x - $width, 'y' => $y + $topTaper],
                ['x' => $x - $width, 'y' => $y + $height - $bottomTaper],
                ['x' => $x, 'y' => $y + $height],
            ],
            'right' => [
                ['x' => $x, 'y' => $y],
                ['x' => $x + $width, 'y' => $y + $topTaper],
                ['x' => $x + $width, 'y' => $y + $height - $bottomTaper],
                ['x' => $x, 'y' => $y + $height],
            ],
            default => throw new InvalidArgumentException('Glue flap direction must be left or right.'),
        };

        return [
            'cut' => [
                $this->line($points[0]['x'], $points[0]['y'], $points[1]['x'], $points[1]['y']),
                $this->line($points[1]['x'], $points[1]['y'], $points[2]['x'], $points[2]['y']),
                $this->line($points[2]['x'], $points[2]['y'], $points[3]['x'], $points[3]['y']),
            ],
            'crease' => [
                $this->line($points[0]['x'], $points[0]['y'], $points[3]['x'], $points[3]['y']),
            ],
            'glue' => [
                ['points' => $points],
            ],
        ];
    }

    /**
     * Build a dust flap with a configurable tapered profile.
     *
     * The local outline starts at the left hinge, follows the outside edge
     * counter-clockwise, and closes along the hinge/baseline. When profile
     * values are omitted, conservative proportions are used as a starting
     * point; production templates should override them with board-specific
     * measurements.
     *
     * @return array{cut: array<int, array{x1: float, y1: float, x2: float, y2: float}>, crease: array<int, array{x1: float, y1: float, x2: float, y2: float}>}
     */
    protected function dustFlap(
        float $x,
        float $y,
        float $span,
        float $depth,
        string $direction,
        bool $flipHorizontal = false,
        ?float $topLeftInset = null,
        ?float $topRightInset = null,
        ?float $leftToeWidth = null,
        ?float $leftToeDepth = null,
        ?float $rightShoulderInset = null,
        ?float $rightShoulderDepth = null,
        ?float $rightEdgeDepth = null,
    ): array {
        $span = max(0, $span);
        $depth = max(0, $depth);
        $topLeftInset ??= min($span * 0.12, $depth * 0.4);
        $topRightInset ??= min($span * 0.15, $depth * 0.4);
        $leftToeWidth ??= min($span * 0.05, $depth * 0.15);
        $leftToeDepth ??= min($depth * 0.15, $span * 0.05);
        $rightShoulderInset ??= min($span * 0.08, $depth * 0.15);
        $rightShoulderDepth ??= $depth * 0.25;
        $rightEdgeDepth ??= $depth * 0.15;

        $topLeftInset = $this->clamp($topLeftInset, 0, $span);
        $topRightInset = $this->clamp($topRightInset, 0, $span - $topLeftInset);
        $leftToeWidth = $this->clamp($leftToeWidth, 0, $topLeftInset);
        $leftToeDepth = $this->clamp($leftToeDepth, 0, $depth);
        $rightShoulderInset = $this->clamp($rightShoulderInset, 0, $span);
        $rightShoulderDepth = $this->clamp($rightShoulderDepth, 0, $depth);
        $rightEdgeDepth = $this->clamp($rightEdgeDepth, 0, $rightShoulderDepth);

        $outline = $this->drawPath(
            $x,
            $y,
            $direction,
            function (DielinePath $ctx) use (
                $span,
                $depth,
                $topLeftInset,
                $topRightInset,
                $leftToeWidth,
                $leftToeDepth,
                $rightShoulderInset,
                $rightShoulderDepth,
                $rightEdgeDepth,
            ): void {
                $ctx->moveTo(0, 0)
                    ->lineTo($leftToeWidth, -$leftToeDepth)
                    ->lineTo($topLeftInset, -$depth)
                    ->lineTo($span - $topRightInset, -$depth)
                    ->lineTo($span - $rightShoulderInset, -$rightShoulderDepth)
                    ->lineTo($span, -$rightEdgeDepth)
                    ->lineTo($span, 0)
                    ->lineTo(0, 0);
            },
            mirrorWidth: $flipHorizontal ? $span : null,
        );

        return [
            'cut' => array_slice($outline, 0, -1),
            'crease' => array_slice($outline, -1),
        ];
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function lockingFlap(float $x, float $y, float $span, float $depth, string $direction): array
    {
        return $this->flap($x, $y, $span, $depth, $direction);
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function bleedBox(float $x, float $y, float $width, float $height, float $bleed): array
    {
        return $this->rectangle($x - $bleed, $y - $bleed, $width + ($bleed * 2), $height + ($bleed * 2));
    }

    /**
     * @return array{points: array<int, array{x: float, y: float}>}
     */
    protected function glueArea(float $x, float $y, float $width, float $height): array
    {
        return ['points' => $this->polygon($x, $y, $width, $height)];
    }

    /**
     * @return array<int, array{x: float, y: float}>
     */
    protected function polygon(float $x, float $y, float $width, float $height): array
    {
        return [
            ['x' => $x, 'y' => $y],
            ['x' => $x + $width, 'y' => $y],
            ['x' => $x + $width, 'y' => $y + $height],
            ['x' => $x, 'y' => $y + $height],
        ];
    }

    /**
     * @return array{x: float, y: float, text: string}
     */
    protected function label(float $x, float $y, string $text): array
    {
        return compact('x', 'y', 'text');
    }

    private function clamp(float $value, float $minimum, float $maximum): float
    {
        return min(max($value, $minimum), $maximum);
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    private function flap(float $x, float $y, float $span, float $depth, string $direction): array
    {
        return match ($direction) {
            'top' => [
                $this->line($x, $y, $x, $y - $depth),
                $this->line($x, $y - $depth, $x + $span, $y - $depth),
                $this->line($x + $span, $y - $depth, $x + $span, $y),
            ],
            'bottom' => [
                $this->line($x, $y, $x, $y + $depth),
                $this->line($x, $y + $depth, $x + $span, $y + $depth),
                $this->line($x + $span, $y + $depth, $x + $span, $y),
            ],
            'left' => [
                $this->line($x, $y, $x - $depth, $y),
                $this->line($x - $depth, $y, $x - $depth, $y + $span),
                $this->line($x - $depth, $y + $span, $x, $y + $span),
            ],
            'right' => [
                $this->line($x, $y, $x + $depth, $y),
                $this->line($x + $depth, $y, $x + $depth, $y + $span),
                $this->line($x + $depth, $y + $span, $x, $y + $span),
            ],
        };
    }

    /**
     * @return array{x1: float, y1: float, x2: float, y2: float}
     */
    private function directionalLine(
        float $originX,
        float $originY,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        string $direction,
    ): array {
        [$absoluteX1, $absoluteY1] = $this->directionalPoint($originX, $originY, $x1, $y1, $direction);
        [$absoluteX2, $absoluteY2] = $this->directionalPoint($originX, $originY, $x2, $y2, $direction);

        return $this->line($absoluteX1, $absoluteY1, $absoluteX2, $absoluteY2);
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    private function directionalArcLines(
        float $originX,
        float $originY,
        float $centerX,
        float $centerY,
        float $radius,
        float $startDegrees,
        float $endDegrees,
        int $segments,
        string $direction,
    ): array {
        if ($radius <= 0) {
            return [];
        }

        $lines = [];
        $previousPoint = null;

        for ($segment = 0; $segment <= $segments; $segment++) {
            $degrees = $startDegrees + (($endDegrees - $startDegrees) * ($segment / $segments));
            $point = [
                $centerX + ($radius * cos(deg2rad($degrees))),
                $centerY + ($radius * sin(deg2rad($degrees))),
            ];

            if ($previousPoint !== null) {
                $lines[] = $this->directionalLine(
                    $originX,
                    $originY,
                    $previousPoint[0],
                    $previousPoint[1],
                    $point[0],
                    $point[1],
                    $direction,
                );
            }

            $previousPoint = $point;
        }

        return $lines;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function directionalPoint(float $originX, float $originY, float $x, float $y, string $direction): array
    {
        return match ($direction) {
            'top' => [$originX + $x, $originY + $y],
            'bottom' => [$originX + $x, $originY - $y],
            'left' => [$originX + $y, $originY + $x],
            'right' => [$originX - $y, $originY + $x],
        };
    }
}

<?php

namespace App\Services\Dielines\Concerns;

use App\Services\Dielines\Geometry\DielinePath;

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
    protected function drawPath(float $x, float $y, string $direction, callable $draw, int $arcSegments = 8): array
    {
        $path = new DielinePath($x, $y, $direction, $arcSegments);
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
    protected function tuckFlap(float $x, float $y, float $span, float $depth, string $direction): array
    {
        return $this->flap($x, $y, $span, $depth, $direction);
    }

    /**
     * @return array{cut: array<int, array{x1: float, y1: float, x2: float, y2: float}>, crease: array<int, array{x1: float, y1: float, x2: float, y2: float}>}
     */
    protected function roundedTuckFlap(
        float $x,
        float $y,
        float $span,
        float $depth,
        string $direction,
        float $radius = 6,
        float $slitLength = 25,
        float $slitOffset = 5,
        int $arcSegments = 6,
    ): array {
        $radius = min(max(0, $radius), $span / 2, $depth);
        $slitLength = min(max(0, $slitLength), $span / 2);
        $slitOffset = max(0, $slitOffset);
        $arcSegments = max(2, $arcSegments);

        $cut = $this->drawPath(
            $x,
            $y,
            $direction,
            function (DielinePath $ctx) use ($span, $depth, $radius, $slitLength, $slitOffset): void {
                $ctx->moveTo(0, 0)
                    ->lineTo(0, -$depth + $radius)
                    ->arcTo(0, -$depth, $radius, -$depth, $radius)
                    ->lineTo($span - $radius, -$depth)
                    ->arcTo($span, -$depth, $span, -$depth + $radius, $radius)
                    ->lineTo($span, 0);

                $ctx->moveTo(0, -$depth + $radius - $slitOffset)
                    ->lineTo($slitLength, -$depth + $radius - $slitOffset)
                    ->lineTo($slitLength, -$depth + $radius + $slitOffset);

                $ctx->moveTo($span, -$depth + $radius - $slitOffset)
                    ->lineTo($span - $slitLength, -$depth + $radius - $slitOffset)
                    ->lineTo($span - $slitLength, -$depth + $radius + $slitOffset);
            },
            $arcSegments,
        );

        return [
            'cut' => $cut,
            'crease' => $this->drawPath(
                $x,
                $y,
                $direction,
                fn (DielinePath $ctx): DielinePath => $ctx
                    ->moveTo($slitLength, -$depth + $radius)
                    ->lineTo($span - $slitLength, -$depth + $radius),
                $arcSegments,
            ),
        ];
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    protected function dustFlap(float $x, float $y, float $span, float $depth, string $direction): array
    {
        return $this->flap($x, $y, $span, $depth, $direction);
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

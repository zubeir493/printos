<?php

namespace App\Services\Dielines\Geometry;

class DielinePath
{
    /**
     * @var array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    private array $lines = [];

    /**
     * @var array{x: float, y: float}|null
     */
    private ?array $currentPoint = null;

    public function __construct(
        private float $originX = 0,
        private float $originY = 0,
        private string $direction = 'top',
        private int $arcSegments = 8,
    ) {}

    public function moveTo(float $x, float $y): self
    {
        $this->currentPoint = ['x' => $x, 'y' => $y];

        return $this;
    }

    public function lineTo(float $x, float $y): self
    {
        if ($this->currentPoint === null) {
            return $this->moveTo($x, $y);
        }

        if ($this->distance($this->currentPoint, ['x' => $x, 'y' => $y]) <= 0.000001) {
            $this->currentPoint = ['x' => $x, 'y' => $y];

            return $this;
        }

        $this->pushLine($this->currentPoint['x'], $this->currentPoint['y'], $x, $y);
        $this->currentPoint = ['x' => $x, 'y' => $y];

        return $this;
    }

    public function arcTo(float $x1, float $y1, float $x2, float $y2, float $radius): self
    {
        if ($this->currentPoint === null) {
            return $this->moveTo($x1, $y1);
        }

        if ($radius <= 0) {
            return $this->lineTo($x1, $y1);
        }

        $start = $this->currentPoint;
        $corner = ['x' => $x1, 'y' => $y1];
        $end = ['x' => $x2, 'y' => $y2];
        $fromCorner = $this->normalize($start['x'] - $corner['x'], $start['y'] - $corner['y']);
        $toEnd = $this->normalize($end['x'] - $corner['x'], $end['y'] - $corner['y']);

        if ($fromCorner === null || $toEnd === null) {
            return $this->lineTo($x1, $y1);
        }

        $dot = max(-1, min(1, ($fromCorner['x'] * $toEnd['x']) + ($fromCorner['y'] * $toEnd['y'])));
        $angle = acos($dot);

        if ($angle <= 0.000001 || abs(M_PI - $angle) <= 0.000001) {
            return $this->lineTo($x1, $y1);
        }

        $distanceToTangent = min(
            $radius / tan($angle / 2),
            $this->distance($start, $corner),
            $this->distance($end, $corner),
        );

        $tangentStart = [
            'x' => $corner['x'] + ($fromCorner['x'] * $distanceToTangent),
            'y' => $corner['y'] + ($fromCorner['y'] * $distanceToTangent),
        ];
        $tangentEnd = [
            'x' => $corner['x'] + ($toEnd['x'] * $distanceToTangent),
            'y' => $corner['y'] + ($toEnd['y'] * $distanceToTangent),
        ];

        $this->lineTo($tangentStart['x'], $tangentStart['y']);

        $cross = ($fromCorner['x'] * $toEnd['y']) - ($fromCorner['y'] * $toEnd['x']);
        $normal = $cross < 0
            ? $this->rotateClockwise($fromCorner)
            : $this->rotateCounterClockwise($fromCorner);
        $center = [
            'x' => $tangentStart['x'] + ($normal['x'] * $radius),
            'y' => $tangentStart['y'] + ($normal['y'] * $radius),
        ];

        $startAngle = atan2($tangentStart['y'] - $center['y'], $tangentStart['x'] - $center['x']);
        $endAngle = atan2($tangentEnd['y'] - $center['y'], $tangentEnd['x'] - $center['x']);

        if ($cross < 0 && $endAngle <= $startAngle) {
            $endAngle += M_PI * 2;
        }

        if ($cross >= 0 && $endAngle >= $startAngle) {
            $endAngle -= M_PI * 2;
        }

        for ($segment = 1; $segment <= $this->arcSegments; $segment++) {
            $progress = $segment / $this->arcSegments;
            $angle = $startAngle + (($endAngle - $startAngle) * $progress);

            $this->lineTo(
                $center['x'] + ($radius * cos($angle)),
                $center['y'] + ($radius * sin($angle)),
            );
        }

        return $this;
    }

    /**
     * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    private function pushLine(float $x1, float $y1, float $x2, float $y2): void
    {
        [$absoluteX1, $absoluteY1] = $this->point($x1, $y1);
        [$absoluteX2, $absoluteY2] = $this->point($x2, $y2);

        $this->lines[] = [
            'x1' => $absoluteX1,
            'y1' => $absoluteY1,
            'x2' => $absoluteX2,
            'y2' => $absoluteY2,
        ];
    }

    /**
     * @return array{x: float, y: float}|null
     */
    private function normalize(float $x, float $y): ?array
    {
        $length = sqrt(($x * $x) + ($y * $y));

        if ($length <= 0.000001) {
            return null;
        }

        return ['x' => $x / $length, 'y' => $y / $length];
    }

    /**
     * @param  array{x: float, y: float}  $first
     * @param  array{x: float, y: float}  $second
     */
    private function distance(array $first, array $second): float
    {
        return sqrt((($first['x'] - $second['x']) ** 2) + (($first['y'] - $second['y']) ** 2));
    }

    /**
     * @param  array{x: float, y: float}  $point
     * @return array{x: float, y: float}
     */
    private function rotateClockwise(array $point): array
    {
        return ['x' => $point['y'], 'y' => -$point['x']];
    }

    /**
     * @param  array{x: float, y: float}  $point
     * @return array{x: float, y: float}
     */
    private function rotateCounterClockwise(array $point): array
    {
        return ['x' => -$point['y'], 'y' => $point['x']];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function point(float $x, float $y): array
    {
        return match ($this->direction) {
            'top' => [$this->originX + $x, $this->originY + $y],
            'bottom' => [$this->originX + $x, $this->originY - $y],
            'left' => [$this->originX + $y, $this->originY + $x],
            'right' => [$this->originX - $y, $this->originY + $x],
        };
    }
}

<?php

namespace App\Services\Dielines\Geometry;

class DielineCanvas
{
    public string $strokeStyle = 'cut';

    /**
     * @var array<string, array<int, array{x1: float, y1: float, x2: float, y2: float}>>
     */
    private array $layers = [
        'cut' => [],
        'crease' => [],
        'bleed' => [],
    ];

    /**
     * @var array<int, array{x1: float, y1: float, x2: float, y2: float}>
     */
    private array $path = [];

    /**
     * @var array{x: float, y: float}|null
     */
    private ?array $currentPoint = null;

    /**
     * @var array<int, array{x: float, y: float}>
     */
    private array $stateStack = [];

    private float $translateX = 0;

    private float $translateY = 0;

    public function __construct(private int $arcSegments = 8) {}

    public function beginPath(): void
    {
        $this->path = [];
        $this->currentPoint = null;
    }

    public function moveTo(float $x, float $y): void
    {
        $this->currentPoint = $this->point($x, $y);
    }

    public function lineTo(float $x, float $y): void
    {
        $point = $this->point($x, $y);

        if ($this->currentPoint === null) {
            $this->currentPoint = $point;

            return;
        }

        if ($this->distance($this->currentPoint, $point) > 0.000001) {
            $this->path[] = [
                'x1' => $this->currentPoint['x'],
                'y1' => $this->currentPoint['y'],
                'x2' => $point['x'],
                'y2' => $point['y'],
            ];
        }

        $this->currentPoint = $point;
    }

    public function arcTo(float $x1, float $y1, float $x2, float $y2, float $radius): void
    {
        if ($this->currentPoint === null || $radius <= 0) {
            $this->lineTo($x1, $y1);

            return;
        }

        $start = $this->untranslated($this->currentPoint);
        $corner = ['x' => $x1, 'y' => $y1];
        $end = ['x' => $x2, 'y' => $y2];
        $fromCorner = $this->normalize($start['x'] - $corner['x'], $start['y'] - $corner['y']);
        $toEnd = $this->normalize($end['x'] - $corner['x'], $end['y'] - $corner['y']);

        if ($fromCorner === null || $toEnd === null) {
            $this->lineTo($x1, $y1);

            return;
        }

        $dot = max(-1, min(1, ($fromCorner['x'] * $toEnd['x']) + ($fromCorner['y'] * $toEnd['y'])));
        $angle = acos($dot);

        if ($angle <= 0.000001 || abs(M_PI - $angle) <= 0.000001) {
            $this->lineTo($x1, $y1);

            return;
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
            ? ['x' => $fromCorner['y'], 'y' => -$fromCorner['x']]
            : ['x' => -$fromCorner['y'], 'y' => $fromCorner['x']];
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
            $arcAngle = $startAngle + (($endAngle - $startAngle) * $progress);

            $this->lineTo(
                $center['x'] + ($radius * cos($arcAngle)),
                $center['y'] + ($radius * sin($arcAngle)),
            );
        }
    }

    public function stroke(): void
    {
        $layer = $this->layer();
        array_push($this->layers[$layer], ...$this->path);
        $this->beginPath();
    }

    public function save(): void
    {
        $this->stateStack[] = ['x' => $this->translateX, 'y' => $this->translateY];
    }

    public function restore(): void
    {
        $state = array_pop($this->stateStack);

        if ($state === null) {
            return;
        }

        $this->translateX = $state['x'];
        $this->translateY = $state['y'];
    }

    public function translate(float $x, float $y): void
    {
        $this->translateX += $x;
        $this->translateY += $y;
    }

    /**
     * @return array<string, array<int, array{x1: float, y1: float, x2: float, y2: float}>>
     */
    public function layers(): array
    {
        return $this->layers;
    }

    /**
     * @return array{x: float, y: float}
     */
    private function point(float $x, float $y): array
    {
        return ['x' => $x + $this->translateX, 'y' => $y + $this->translateY];
    }

    /**
     * @param  array{x: float, y: float}  $point
     * @return array{x: float, y: float}
     */
    private function untranslated(array $point): array
    {
        return ['x' => $point['x'] - $this->translateX, 'y' => $point['y'] - $this->translateY];
    }

    private function layer(): string
    {
        return match (strtolower($this->strokeStyle)) {
            'crease', 'fold', 'folding', 'black' => 'crease',
            'bleed', 'orange', 'amber' => 'bleed',
            default => 'cut',
        };
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
}

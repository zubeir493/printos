<?php

use App\Services\Dielines\Geometry\PanelProfile;
use App\Services\Dielines\Templates\AutoBottomTuckTopTemplate;
use App\Services\Dielines\Templates\BurgerBoxTemplate;
use App\Services\Dielines\Templates\CarryBagTemplate;
use App\Services\Dielines\Templates\ClamshellBurgerBoxTemplate;
use App\Services\Dielines\Templates\FlipTopBurgerBoxTemplate;
use App\Services\Dielines\Templates\FoodTrayTemplate;
use App\Services\Dielines\Templates\FriesScoopTemplate;
use App\Services\Dielines\Templates\FullOverlapCartonTemplate;
use App\Services\Dielines\Templates\GableBoxTemplate;
use App\Services\Dielines\Templates\HangTabCartonTemplate;
use App\Services\Dielines\Templates\LockTabPizzaBoxTemplate;
use App\Services\Dielines\Templates\OpenFoodTrayTemplate;
use App\Services\Dielines\Templates\PizzaBoxTemplate;
use App\Services\Dielines\Templates\ReverseTuckFlapBoxTemplate;
use App\Services\Dielines\Templates\RolloverPizzaBoxTemplate;
use App\Services\Dielines\Templates\SandwichWedgeTemplate;
use App\Services\Dielines\Templates\SealEndCartonTemplate;
use App\Services\Dielines\Templates\SleeveBoxTemplate;
use App\Services\Dielines\Templates\SnapLockBottomTemplate;
use App\Services\Dielines\Templates\StraightTuckFlapTemplate;
use App\Services\Dielines\Templates\VialInsertTemplate;

/**
 * Read only manufacturing paths from an independently captured public preview.
 *
 * @return array<int, array{x1: float, y1: float, x2: float, y2: float}>
 */
function sourceDielineLines(string $filename, string $layer, ?string $directory = null): array
{
    $document = new DOMDocument;
    $document->load(($directory ?? __DIR__.'/../Support/Dielines').'/'.$filename);
    $lines = [];

    if ($filename === 'open-ended-sleeve.svg') {
        if (! in_array($layer, ['cut', 'crease'], true)) {
            return [];
        }
        $xpath = new DOMXPath($document);
        $group = $layer === 'cut' ? 'Cut' : 'Fold';
        foreach ($xpath->query('//*[@id="'.$group.'"]/*') as $path) {
            preg_match_all('/[MLZ]|-?\d+(?:\.\d+)?/', $path->getAttribute('d'), $matches);
            $tokens = $matches[0];
            $cursor = null;
            $start = null;
            for ($index = 0; $index < count($tokens);) {
                $command = $tokens[$index++];
                if ($command === 'Z') {
                    $point = $start;
                } else {
                    $point = [((float) $tokens[$index++] - 45) * 25.4 / 72, ((float) $tokens[$index++] - 45) * 25.4 / 72];
                }
                if ($command !== 'M' && $cursor !== null && $point !== $cursor) {
                    $lines[] = ['x1' => $cursor[0], 'y1' => $cursor[1], 'x2' => $point[0], 'y2' => $point[1]];
                }
                $cursor = $point;
                if ($command === 'M') {
                    $start = $point;
                }
            }
        }

        return $lines;
    }

    foreach ($document->getElementsByTagName('*') as $element) {
        $class = $element->getAttribute('class');

        $pattern = match ($layer) {
            'cut' => '/\bcolor-1\b/',
            'perforation' => '/\bcolor-2\b/',
            'cut_crease' => '/\bcolor-5\b/',
            default => '/\bcolor-3\b/',
        };

        if (str_contains($class, 'dmt-line') || ! preg_match($pattern, $class)) {
            continue;
        }

        if ($element->localName === 'line') {
            $lines[] = [
                'x1' => (float) $element->getAttribute('x1'), 'y1' => (float) $element->getAttribute('y1'),
                'x2' => (float) $element->getAttribute('x2'), 'y2' => (float) $element->getAttribute('y2'),
            ];

            continue;
        }

        if ($element->localName === 'circle') {
            $cx = (float) $element->getAttribute('cx');
            $cy = (float) $element->getAttribute('cy');
            $radius = (float) $element->getAttribute('r');
            $segments = max(8, (int) ceil(M_PI / acos(max(-1, 1 - 0.002 / $radius))));

            for ($index = 0; $index < $segments; $index++) {
                $start = 2 * M_PI * $index / $segments;
                $end = 2 * M_PI * ($index + 1) / $segments;
                $lines[] = ['x1' => $cx + $radius * cos($start), 'y1' => $cy + $radius * sin($start), 'x2' => $cx + $radius * cos($end), 'y2' => $cy + $radius * sin($end)];
            }

            continue;
        }

        if ($element->localName !== 'path' || ! $element->hasAttribute('data-r')) {
            throw new RuntimeException('Unsupported manufacturing element: '.$element->localName);
        }

        preg_match_all('/-?\d+(?:\.\d+)?(?:e[+-]?\d+)?/i', $element->getAttribute('d'), $numbers);
        $numbers = array_map('floatval', $numbers[0]);
        $cx = (float) $element->getAttribute('data-cx');
        $cy = (float) $element->getAttribute('data-cy');
        $radius = (float) $element->getAttribute('data-r');
        $start = atan2($numbers[1] - $cy, $numbers[0] - $cx);
        $end = atan2($numbers[8] - $cy, $numbers[7] - $cx);

        if ($numbers[6] === 0.0 && $end >= $start) {
            $end -= 2 * M_PI;
        } elseif ($numbers[6] === 1.0 && $end <= $start) {
            $end += 2 * M_PI;
        }

        $segments = max(2, (int) ceil(abs($end - $start) / (2 * acos(max(-1, 1 - 0.002 / $radius)))));
        $previous = [$numbers[0], $numbers[1]];

        for ($index = 1; $index <= $segments; $index++) {
            $angle = $start + ($end - $start) * $index / $segments;
            $point = [$cx + $radius * cos($angle), $cy + $radius * sin($angle)];
            $lines[] = ['x1' => $previous[0], 'y1' => $previous[1], 'x2' => $point[0], 'y2' => $point[1]];
            $previous = $point;
        }
    }

    return $lines;
}

/** @return array<string, float|int> */
function sourceDielineDimensions(string $key): array
{
    return json_decode(file_get_contents(__DIR__.'/../Support/Dielines/reference-dimensions.json'), true, flags: JSON_THROW_ON_ERROR)[$key];
}

/**
 * @param  array{x1: float, y1: float, x2: float, y2: float}  $line
 */
function dielinePointDistance(float $x, float $y, array $line): float
{
    $dx = $line['x2'] - $line['x1'];
    $dy = $line['y2'] - $line['y1'];
    $lengthSquared = $dx * $dx + $dy * $dy;
    $fraction = $lengthSquared > 0 ? max(0, min(1, (($x - $line['x1']) * $dx + ($y - $line['y1']) * $dy) / $lengthSquared)) : 0;

    return hypot($x - $line['x1'] - $fraction * $dx, $y - $line['y1'] - $fraction * $dy);
}

/**
 * @param  array<int, array{x1: float, y1: float, x2: float, y2: float}>  $from
 * @param  array<int, array{x1: float, y1: float, x2: float, y2: float}>  $to
 */
function dielineContourDeviation(array $from, array $to, ?array &$worstPoint = null): float
{
    $maximum = 0.0;

    foreach ($from as $line) {
        foreach ([0, 0.5, 1] as $fraction) {
            $x = $line['x1'] + ($line['x2'] - $line['x1']) * $fraction;
            $y = $line['y1'] + ($line['y2'] - $line['y1']) * $fraction;
            $nearest = INF;

            foreach ($to as $other) {
                $nearest = min($nearest, dielinePointDistance($x, $y, $other));
            }

            if ($nearest > $maximum) {
                $maximum = $nearest;
                $worstPoint = [$x, $y];
            }
        }
    }

    return $maximum;
}

it('splits source paths at column boundaries before projecting unequal panel widths', function (): void {
    $layers = (new PanelProfile)->project('carry-bag', fn (float $x, float $y): array => [$x <= 100 ? $x : 100 + 2 * ($x - 100), $y], [], [100]);

    expect($layers['crease'])
        ->toContain(['x1' => 75.0, 'y1' => 25.0, 'x2' => 100.0, 'y2' => 0.0])
        ->toContain(['x1' => 100.0, 'y1' => 0.0, 'x2' => 150.0, 'y2' => -25.0]);
});

it('keeps both steam vents round at their declared diameter after unequal pizza resizing', function (string $class, array $fractions): void {
    $geometry = (new $class)->generate(['l' => 260, 'w' => 180, 'h' => 45, 'vent_diameter' => 12]);

    foreach ($fractions as $fraction) {
        $cx = 260 * $fraction;
        $cy = -202.5;
        $hole = array_filter($geometry['layers']['cut'], fn (array $line): bool => abs(hypot($line['x1'] - $cx, $line['y1'] - $cy) - 6) < 0.00001);
        expect(count($hole))->toBeGreaterThan(40);

        foreach ($hole as $line) {
            expect(hypot($line['x2'] - $cx, $line['y2'] - $cy))->toEqualWithDelta(6, 0.00001);
        }
    }
})->with([
    [PizzaBoxTemplate::class, [.25, .75]],
    [LockTabPizzaBoxTemplate::class, [1 / 3, 2 / 3]],
]);

it('matches every fries scoop cut and fold to the public reference within 0.03 millimetres', function (): void {
    $geometry = (new FriesScoopTemplate)->generate(sourceDielineDimensions('fries-scoop'));

    foreach (['cut', 'crease'] as $layer) {
        $source = sourceDielineLines('fries-scoop.svg', $layer);
        $actual = $geometry['layers'][$layer];

        expect($source)->not->toBeEmpty();
        $point = null;
        $distance = dielineContourDeviation($source, $actual, $point);
        $this->assertLessThan(0.03, $distance, $layer.' source point '.json_encode($point));
        $distance = dielineContourDeviation($actual, $source, $point);
        $this->assertLessThan(0.03, $distance, $layer.' output point '.json_encode($point));
    }
});

it('copies the replacement and variant source contours without adding or dropping manufacturing paths', function (string $class, string $fixture): void {
    $geometry = (new $class)->generate(sourceDielineDimensions((new $class)->key()));

    $template = new $class;
    $preview = new DOMDocument;
    $preview->load(__DIR__.'/../../public/dieline-assets/references/'.$template->key().'.svg');
    expect($preview->documentElement->namespaceURI)->toBe('http://www.w3.org/2000/svg')
        ->and($preview->documentElement->getAttribute('viewBox'))->not->toBeEmpty()
        ->and($preview->documentElement->getAttribute('data-original-drawing'))->toBe($template->referenceDrawingUrl())
        ->and($preview->getElementsByTagName('script')->length)->toBe(0)
        ->and($preview->getElementsByTagName('foreignObject')->length)->toBe(0);

    foreach (['cut', 'crease', 'cut_crease', 'perforation'] as $layer) {
        $source = sourceDielineLines($fixture, $layer);
        expect(sourceDielineLines($fixture, $layer, __DIR__.'/../../public/dieline-assets/references'))->toBe($source);
        $actual = $geometry['layers'][$layer];

        if ($source === []) {
            expect($actual)->toBeEmpty();

            continue;
        }

        expect($actual)->not->toBeEmpty();
        $point = null;
        $this->assertLessThan(0.01, dielineContourDeviation($source, $actual, $point), $layer.' source '.json_encode($point));
        $this->assertLessThan(0.01, dielineContourDeviation($actual, $source, $point), $layer.' output '.json_encode($point));
    }
})->with([
    [PizzaBoxTemplate::class, 'pizza-box.svg'],
    [LockTabPizzaBoxTemplate::class, 'lock-tab-pizza-box.svg'],
    [ClamshellBurgerBoxTemplate::class, 'clamshell-burger-box.svg'],
    [RolloverPizzaBoxTemplate::class, 'rollover-pizza-box.svg'],
    [FlipTopBurgerBoxTemplate::class, 'flip-top-burger-box.svg'],
    [CarryBagTemplate::class, 'carry-bag.svg'],
    [SandwichWedgeTemplate::class, 'sandwich-wedge.svg'],
    [GableBoxTemplate::class, 'gable-box.svg'],
    [SealEndCartonTemplate::class, 'seal-end-carton.svg'],
    [VialInsertTemplate::class, 'vial-insert.svg'],
    [AutoBottomTuckTopTemplate::class, 'auto-bottom-tuck-top.svg'],
    [ReverseTuckFlapBoxTemplate::class, 'reverse-tuck-flap-box.svg'],
    [StraightTuckFlapTemplate::class, 'straight-tuck-flap.svg'],
    [SnapLockBottomTemplate::class, 'snap-lock-bottom-tuck-top.svg'],
    [FullOverlapCartonTemplate::class, 'full-overlap-carton.svg'],
    [FoodTrayTemplate::class, 'four-corner-food-tray.svg'],
    [SleeveBoxTemplate::class, 'open-ended-sleeve.svg'],
]);

it('uses the separate scoop widths and circular camber rather than a tray base', function (): void {
    $geometry = (new FriesScoopTemplate)->generate(['l' => 120, 'base_length' => 60, 'w' => 45, 'h' => 130, 'camber' => 20]);

    expect($geometry['layers']['crease'])
        ->toContain(['x1' => -60.0, 'y1' => -152.5, 'x2' => -30.0, 'y2' => -22.5])
        ->and((new FriesScoopTemplate)->baseDimensionLabels()['l'])->toBe('Top width')
        ->and($geometry['layers']['cut'])->not->toHaveCount(4);
});

it('matches the burger tooling including locking slots and perforations within 0.03 millimetres', function (): void {
    $geometry = (new BurgerBoxTemplate)->generate(sourceDielineDimensions('burger-box'));

    foreach (['cut', 'crease', 'cut_crease', 'perforation'] as $layer) {
        $source = sourceDielineLines('burger-box.svg', $layer);
        $actual = $geometry['layers'][$layer];
        expect($source)->not->toBeEmpty();
        $point = null;
        $this->assertLessThan(0.03, dielineContourDeviation($source, $actual, $point), $layer.' source point '.json_encode($point));
        $this->assertLessThan(0.03, dielineContourDeviation($actual, $source, $point), $layer.' output point '.json_encode($point));
    }
});

it('matches the open tray cuts and scores with the source caliper relief', function (): void {
    $geometry = (new OpenFoodTrayTemplate)->generate(sourceDielineDimensions('open-food-tray'));

    foreach (['cut', 'crease'] as $layer) {
        $source = sourceDielineLines('open-food-tray.svg', $layer);
        $point = null;
        $this->assertLessThan(0.001, dielineContourDeviation($source, $geometry['layers'][$layer], $point));
        $this->assertLessThan(0.001, dielineContourDeviation($geometry['layers'][$layer], $source, $point));
    }
});

it('matches the pharmacy hanger including the two Euro slots within 0.03 millimetres', function (): void {
    $geometry = (new HangTabCartonTemplate)->generate(sourceDielineDimensions('hang-tab-carton'));

    foreach (['cut', 'crease'] as $layer) {
        $source = sourceDielineLines('hang-tab-carton.svg', $layer);
        $point = null;
        $this->assertLessThan(0.03, dielineContourDeviation($source, $geometry['layers'][$layer], $point), $layer.' source '.json_encode($point));
        $this->assertLessThan(0.03, dielineContourDeviation($geometry['layers'][$layer], $source, $point), $layer.' output '.json_encode($point));
    }
});

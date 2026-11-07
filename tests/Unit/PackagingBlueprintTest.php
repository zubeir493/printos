<?php

use App\Services\Dielines\DielineTemplateContract;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Renderers\DxfDielineRenderer;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use App\Services\Dielines\Templates\BurgerBoxTemplate;
use App\Services\Dielines\Templates\CarryBagTemplate;
use App\Services\Dielines\Templates\GableBoxTemplate;
use App\Services\Dielines\Templates\PizzaBoxTemplate;
use App\Services\Dielines\Templates\SandwichWedgeTemplate;
use App\Services\Dielines\Templates\VialInsertTemplate;

function blueprintPointKey(float $x, float $y): string
{
    return sprintf('%.5f,%.5f', abs($x) < 0.000001 ? 0 : $x, abs($y) < 0.000001 ? 0 : $y);
}

it('registers distinct packaging additions alongside the seven existing constructions', function (): void {
    $types = (new DielineTemplateRegistry)->fallbackTypes();

    expect($types)->toHaveCount(21)
        ->and(array_values($types))->toHaveCount(count(array_unique($types)));

    $names = array_map(fn (string $class): string => (new $class)->name(), $types);

    expect(array_values($names))->toHaveCount(count(array_unique($names)));

    foreach ($names as $name) {
        expect($name)->not->toMatch('/\d|ECMA|FEFCO/')
            ->and(str_word_count($name))->toBeLessThanOrEqual(4);
    }
});

it('exposes every adjustable measurement and scales all layers inside the export bounds', function (string $class): void {
    /** @var DielineTemplateContract $template */
    $template = new $class;
    $defaults = $template->defaults();
    $fieldKeys = array_column($template->advancedFields(), 'key');

    expect(array_diff(array_keys($defaults), ['l', 'w', 'h'], $fieldKeys))->toBeEmpty();

    foreach ($template->advancedFields() as $field) {
        expect($defaults)->toHaveKey($field['key']);
    }

    foreach ([0.65, 1, 1.7] as $scale) {
        $dimensions = array_map(fn (float|int $value): float => $value * $scale, $defaults);

        if (isset($dimensions['vial_count'])) {
            $dimensions['vial_count'] = $defaults['vial_count'];
        }

        $geometry = $template->generate($dimensions);
        ['x' => $left, 'y' => $top, 'width' => $width, 'height' => $height] = $geometry['bounds'];

        expect($width)->toBeGreaterThan(0)
            ->and($height)->toBeGreaterThan(0)
            ->and($geometry['template'])->toBe($template->key())
            ->and($geometry['name'])->toBe($template->name())
            ->and($geometry['layers']['cut'])->not->toBeEmpty()
            ->and($geometry['layers']['crease'])->not->toBeEmpty();

        foreach (['cut', 'crease', 'cut_crease', 'perforation'] as $layer) {
            foreach ($geometry['layers'][$layer] as $line) {
                foreach ([['x1', 'y1'], ['x2', 'y2']] as [$xKey, $yKey]) {
                    expect(is_finite($line[$xKey]))->toBeTrue()
                        ->and(is_finite($line[$yKey]))->toBeTrue()
                        ->and($line[$xKey])->toBeBetween($left - 0.000001, $left + $width + 0.000001)
                        ->and($line[$yKey])->toBeBetween($top - 0.000001, $top + $height + 0.000001);
                }
            }
        }

        foreach ($geometry['layers']['glue'] as $area) {
            foreach ($area['points'] as $point) {
                expect($point['x'])->toBeBetween($left - 0.000001, $left + $width + 0.000001)
                    ->and($point['y'])->toBeBetween($top - 0.000001, $top + $height + 0.000001);
            }
        }

        $svg = (new SvgDielineRenderer)->renderForSvg($geometry);
        $xml = simplexml_load_string($svg);
        $lineCount = count($geometry['layers']['cut']) + count($geometry['layers']['crease']) + count($geometry['layers']['perforation'] ?? []) + count($geometry['layers']['cut_crease'] ?? []);
        $dxf = (new DxfDielineRenderer)->render($geometry);

        expect($xml)->not->toBeFalse()
            ->and(substr_count($svg, '<line '))->toBe($lineCount)
            ->and(substr_count($dxf, "\r\nLINE\r\n"))->toBe($lineCount)
            ->and($svg)->not->toContain('<text');
    }
})->with('packaging blueprints');

it('connects cut contours and terminates relief cuts on folds or other cuts', function (string $class): void {
    $template = new $class;
    $calibration = json_decode(file_get_contents(__DIR__.'/../Support/Dielines/reference-dimensions.json'), true, flags: JSON_THROW_ON_ERROR);
    $geometry = $template->generate($calibration[$template->key()]);
    $degrees = [];
    $segments = [];

    foreach ($geometry['layers']['cut'] as $line) {
        $start = blueprintPointKey($line['x1'], $line['y1']);
        $end = blueprintPointKey($line['x2'], $line['y2']);
        $ends = [$start, $end];
        sort($ends);
        $key = implode('|', $ends);

        expect($start)->not->toBe($end)
            ->and($segments)->not->toHaveKey($key);

        $segments[$key] = true;
        $degrees[$start] = ($degrees[$start] ?? 0) + 1;
        $degrees[$end] = ($degrees[$end] ?? 0) + 1;
    }

    foreach ($degrees as $point => $degree) {
        if ($degree % 2 === 0) {
            continue;
        }

        [$x, $y] = array_map('floatval', explode(',', $point));
        $connected = false;

        $folds = [...$geometry['layers']['crease'], ...$geometry['layers']['cut_crease'], ...$geometry['layers']['perforation']];

        foreach ([...$folds, ...$geometry['layers']['cut']] as $line) {
            $dx = $line['x2'] - $line['x1'];
            $dy = $line['y2'] - $line['y1'];
            $length = hypot($dx, $dy);

            if ($length === 0.0) {
                continue;
            }

            $fraction = (($x - $line['x1']) * $dx + ($y - $line['y1']) * $dy) / ($length ** 2);
            $onFold = in_array($line, $folds, true);
            $onInterior = $fraction > 0.00001 && $fraction < 0.99999;
            $distance = abs(($x - $line['x1']) * $dy - ($y - $line['y1']) * $dx) / $length;

            $reliefOverrun = $class === BurgerBoxTemplate::class ? 0.708 : 0.0001;
            $endDistance = min(hypot($x - $line['x1'], $y - $line['y1']), hypot($x - $line['x2'], $y - $line['y2']));

            if (($onFold || $onInterior) && (($fraction >= -0.00001 && $fraction <= 1.00001 && $distance < 0.0001) || ($onFold && $endDistance <= $reliefOverrun))) {
                $connected = true;

                break;
            }
        }

        if (! $connected) {
            $document = new DOMDocument;
            $document->load(__DIR__.'/../Support/Dielines/'.$template->key().'.svg');

            foreach ($document->getElementsByTagName('*') as $element) {
                if (! preg_match('/\bcolor-1\b/', $element->getAttribute('class')) || str_contains($element->getAttribute('class'), 'dmt-line')) {
                    continue;
                }

                if ($element->localName === 'line') {
                    $endpoints = [[(float) $element->getAttribute('x1'), (float) $element->getAttribute('y1')], [(float) $element->getAttribute('x2'), (float) $element->getAttribute('y2')]];
                } elseif ($element->localName === 'path') {
                    preg_match_all('/-?\d+(?:\.\d+)?(?:e[+-]?\d+)?/i', $element->getAttribute('d'), $numbers);
                    $endpoints = [[(float) $numbers[0][0], (float) $numbers[0][1]], [(float) $numbers[0][7], (float) $numbers[0][8]]];
                } else {
                    continue;
                }

                foreach ($endpoints as [$sourceX, $sourceY]) {
                    if (hypot($x - $sourceX, $y - $sourceY) < 0.0001) {
                        $connected = true;
                    }
                }
            }
        }

        $this->assertTrue($connected, 'Cut endpoint absent from folds and source reliefs '.$point);
    }
})->with('packaging blueprints');

it('preserves the octagonal pizza base corners and its two circular steam vents', function (): void {
    $geometry = (new PizzaBoxTemplate)->generate(json_decode(file_get_contents(__DIR__.'/../Support/Dielines/reference-dimensions.json'), true, flags: JSON_THROW_ON_ERROR)[(new PizzaBoxTemplate)->key()]);

    expect($geometry['layers']['glue'])->toBeEmpty();

    foreach ([50, 150] as $centre) {
        $vent = array_filter($geometry['layers']['cut'], fn (array $line): bool => abs(hypot($line['x1'] - $centre, $line['y1'] + 225) - 10) < 0.00001);
        expect(count($vent))->toBeGreaterThan(50);
    }

    $corners = array_filter($geometry['layers']['crease'], fn (array $line): bool => abs(abs($line['x2'] - $line['x1']) - abs($line['y2'] - $line['y1'])) < 0.00001 && abs($line['x2'] - $line['x1']) > 20);
    expect($corners)->not->toBeEmpty();
});

it('keeps cut edges from crossing and preserves every complete hinge', function (string $class): void {
    $geometry = (new $class)->generate([]);
    $cuts = $geometry['layers']['cut'];
    $crossings = [];
    $cutKeys = [];

    foreach ($cuts as $index => $first) {
        $ends = [blueprintPointKey($first['x1'], $first['y1']), blueprintPointKey($first['x2'], $first['y2'])];
        sort($ends);
        $cutKeys[] = implode('|', $ends);

        foreach (array_slice($cuts, $index + 1) as $second) {
            $cross = static fn (array $line, float $x, float $y): float => ($line['x2'] - $line['x1']) * ($y - $line['y1']) - ($line['y2'] - $line['y1']) * ($x - $line['x1']);

            if ($cross($first, $second['x1'], $second['y1']) * $cross($first, $second['x2'], $second['y2']) < -0.000001
                && $cross($second, $first['x1'], $first['y1']) * $cross($second, $first['x2'], $first['y2']) < -0.000001) {
                $crossings[] = [$first, $second];
            }
        }
    }

    expect($crossings)->toBeEmpty();

    foreach ($geometry['layers']['crease'] as $crease) {
        $ends = [blueprintPointKey($crease['x1'], $crease['y1']), blueprintPointKey($crease['x2'], $crease['y2'])];
        sort($ends);

        expect($cutKeys)->not->toContain(implode('|', $ends));
    }
})->with('packaging blueprints');

it('positions burger base walls and lid independently at their developed panel stations', function (): void {
    $geometry = (new BurgerBoxTemplate)->generate(json_decode(file_get_contents(__DIR__.'/../Support/Dielines/reference-dimensions.json'), true, flags: JSON_THROW_ON_ERROR)[(new BurgerBoxTemplate)->key()]);

    expect($geometry['layers']['crease'])
        ->toContain(['x1' => 0.0, 'y1' => -100.0, 'x2' => 200.0, 'y2' => -100.0])
        ->and($geometry['layers']['perforation'])->not->toBeEmpty();

    $changed = (new BurgerBoxTemplate)->generate(['l' => 160, 'w' => 85, 'h' => 60, 'lid_depth' => 35]);
    expect($changed['layers']['crease'])->toContain(['x1' => 0.0, 'y1' => -85.0, 'x2' => 160.0, 'y2' => -85.0]);
});

it('develops equal triangular sides and an independent seam flap', function (): void {
    $template = new SandwichWedgeTemplate;
    $geometry = $template->generate(['l' => 160, 'w' => 90, 'h' => 12]);
    foreach ([0, -45, -90, -180] as $station) {
        expect($geometry['layers']['crease'])->toContain(['x1' => 0.0, 'y1' => (float) $station, 'x2' => 160.0, 'y2' => (float) $station]);
    }
    expect($template->baseDimensionLabels()['h'])->toBe('Glue flap depth')
        ->and($geometry['bounds']['y'] + $geometry['bounds']['height'])->toBe(12.0);
});
it('scores both bag gussets and folds each pair of cord holes onto its reinforcement', function (): void {
    $geometry = (new CarryBagTemplate)->generate(json_decode(file_get_contents(__DIR__.'/../Support/Dielines/reference-dimensions.json'), true, flags: JSON_THROW_ON_ERROR)[(new CarryBagTemplate)->key()]);
    $gusset = array_filter($geometry['layers']['crease'], fn (array $line): bool => abs($line['x1'] - 125) < 0.00001 && abs($line['x2'] - 125) < 0.00001);
    $length = array_sum(array_map(fn (array $line): float => abs($line['y2'] - $line['y1']), $gusset));
    expect($geometry['bounds'])->toBe(['x' => -13.0, 'y' => -190.0, 'width' => 311.0, 'height' => 228.0])
        ->and($length)->toEqualWithDelta(228, 0.00001);
    foreach ([25, 75, 175, 225] as $cx) {
        foreach ([-135, -165] as $cy) {
            $hole = array_filter($geometry['layers']['cut'], fn (array $line): bool => abs(hypot($line['x1'] - $cx, $line['y1'] - $cy) - 2.5) < .00001);
            expect(count($hole))->toBeGreaterThan(30);
        }
    }
});
it('moves both carry handles together while preserving their circular grip openings', function (): void {
    $geometry = (new GableBoxTemplate)->generate(['l' => 101, 'w' => 50, 'h' => 90, 'handle_height' => 100, 'hole_diameter' => 24]);
    expect($geometry['bounds']['y'])->toBe(-190.0);
    foreach ([25, 176] as $cx) {
        $hole = array_filter($geometry['layers']['cut'], fn (array $line): bool => abs(hypot($line['x1'] - $cx, $line['y1'] + 158.125) - 12) < .00001);
        expect(count($hole))->toBeGreaterThan(70);
    }
});
it('cuts one round opening of the requested diameter with four tapered support walls', function (): void {
    $geometry = (new VialInsertTemplate)->generate(['l' => 100, 'w' => 60, 'h' => 20, 'vial_diameter' => 24, 'wall_taper' => 8]);
    $hole = array_slice($geometry['layers']['cut'], 12);
    expect($hole)->not->toBeEmpty()->and($geometry['layers']['crease'])->toHaveCount(4);
    foreach ($hole as $line) {
        foreach ([['x1', 'y1'], ['x2', 'y2']] as [$x,$y]) {
            expect(hypot($line[$x] - 50, $line[$y] + 30))->toEqualWithDelta(12, .00001);
        }
    }
    expect($geometry['layers']['cut'])->toContain(['x1' => 0.0, 'y1' => 0.0, 'x2' => 8.0, 'y2' => 20.0]);
});

it('rejects insert apertures and tapers that do not fit the platform', function (array $dimensions): void {
    expect(fn (): array => (new VialInsertTemplate)->generate($dimensions))->toThrow(InvalidArgumentException::class);
})->with([
    'too wide' => [['w' => 20, 'vial_diameter' => 25]],
    'zero aperture' => [['vial_diameter' => 0]],
    'negative taper' => [['wall_taper' => -1]],
    'excessive taper' => [['wall_taper' => 60]],
]);
it('retains distinct perforation and cut-crease operations in all export renderings', function (): void {
    $geometry = (new BurgerBoxTemplate)->generate(json_decode(file_get_contents(__DIR__.'/../Support/Dielines/reference-dimensions.json'), true, flags: JSON_THROW_ON_ERROR)[(new BurgerBoxTemplate)->key()]);
    $renderer = new SvgDielineRenderer;

    foreach ([$renderer->render($geometry), $renderer->renderForSvg($geometry), $renderer->renderForPdf($geometry)] as $svg) {
        $xml = simplexml_load_string($svg);
        expect($xml)->not->toBeFalse()
            ->and($svg)->toContain('stroke="#a855f7"', 'stroke="#0891b2"', 'stroke-dasharray=');
    }

    $dxf = (new DxfDielineRenderer)->render($geometry);
    expect(substr_count($dxf, "8\r\nPERFORATION\r\n"))->toBe(count($geometry['layers']['perforation']))
        ->and(substr_count($dxf, "8\r\nCUT_CREASE\r\n"))->toBe(count($geometry['layers']['cut_crease']));
});

it('keeps the triangular end closures and source relief cuts without an invented window', function (): void {
    $template = new SandwichWedgeTemplate;
    $geometry = $template->generate(json_decode(file_get_contents(__DIR__.'/../Support/Dielines/reference-dimensions.json'), true, flags: JSON_THROW_ON_ERROR)[$template->key()]);
    $triangle = array_filter($geometry['layers']['cut'], fn (array $line): bool => hypot($line['x1'] - 200, $line['y1'] + 220) < 0.00001 && hypot($line['x2'] - 295.7627944163, $line['y2'] + 165) < 0.00001);
    expect($template->defaults())->not->toHaveKey('window_inset')
        ->and($triangle)->toHaveCount(1);
    $cuts = array_filter($geometry['layers']['cut'], fn (array $line): bool => min($line['x1'], $line['x2']) > 1 && max($line['x1'], $line['x2']) < 199 && max($line['y1'], $line['y2']) < 0);
    expect($cuts)->toBeEmpty();
});

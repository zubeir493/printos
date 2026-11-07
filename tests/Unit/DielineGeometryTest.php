<?php

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Geometry\DielineCanvas;
use App\Services\Dielines\Geometry\DielinePath;
use App\Services\Dielines\Renderers\DxfDielineRenderer;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use App\Services\Dielines\Templates\AutoBottomTuckTopTemplate;
use App\Services\Dielines\Templates\FoodTrayTemplate;
use App\Services\Dielines\Templates\FullOverlapCartonTemplate;
use App\Services\Dielines\Templates\ReverseTuckFlapBoxTemplate;
use App\Services\Dielines\Templates\SleeveBoxTemplate;
use App\Services\Dielines\Templates\SnapLockBottomTemplate;
use App\Services\Dielines\Templates\StraightTuckFlapTemplate;

function dielineGeometryHelpers(): object
{
    return new class
    {
        use BuildsDielineGeometry;

        public function tuck(float $x, float $y, float $span, float $depth, string $direction): array
        {
            return $this->straightFlap($x, $y, $span, $depth, $direction);
        }

        public function tuckComponent(
            float $x,
            float $y,
            float $span,
            float $closureHeight,
            string $direction,
            float $tuckHeight,
            float $radius,
            float $slitWidth,
            float $slitHeight,
            int $arcSegments,
        ): array {
            return $this->tuckFlapComponent($x, $y, $span, $closureHeight, $direction, $tuckHeight, $radius, $slitWidth, $slitHeight, $arcSegments);
        }

        public function glueFlap(
            float $x,
            float $y,
            float $width,
            float $height,
            string $direction,
            float $topTaper,
            float $bottomTaper,
        ): array {
            return $this->glueFlapComponent($x, $y, $width, $height, $direction, $topTaper, $bottomTaper);
        }

        public function dust(float $x, float $y, float $span, float $depth, string $direction): array
        {
            return $this->dustFlap($x, $y, $span, $depth, $direction);
        }

        public function profiledDust(float $x, float $y, float $span, float $depth, string $direction): array
        {
            return $this->dustFlap(
                $x,
                $y,
                $span,
                $depth,
                $direction,
                topLeftInset: 10,
                topRightInset: 10,
                leftToeWidth: 8,
                leftToeDepth: 8,
                rightShoulderInset: 6,
                rightShoulderDepth: 20,
                rightEdgeDepth: 10,
            );
        }

        public function locking(float $x, float $y, float $span, float $depth, string $direction): array
        {
            return $this->lockingFlap($x, $y, $span, $depth, $direction);
        }

        public function bleed(float $x, float $y, float $width, float $height, float $bleed): array
        {
            return $this->bleedBox($x, $y, $width, $height, $bleed);
        }

        public function glue(float $x, float $y, float $width, float $height): array
        {
            return $this->glueArea($x, $y, $width, $height);
        }
    };
}

it('registers the reverse tuck flap box template', function (): void {
    expect((new DielineTemplateRegistry)->fallbackTypes())
        ->toHaveKey('reverse-tuck-flap-box');
});

it('registers the straight tuck flap template', function (): void {
    expect((new DielineTemplateRegistry)->fallbackTypes())
        ->toHaveKey('straight-tuck-flap');
});

it('registers five additional medical and food packaging templates', function (): void {
    $registry = new DielineTemplateRegistry;

    expect($registry->fallbackTypes())
        ->toHaveKeys([
            'auto-bottom-tuck-top',
            'four-corner-food-tray',
            'full-overlap-carton',
            'open-ended-sleeve',
            'snap-lock-bottom-tuck-top',
        ]);

});

it('generates bounded cut, crease, and glue geometry for the additional templates', function (): void {
    $templates = [
        new AutoBottomTuckTopTemplate,
        new FoodTrayTemplate,
        new FullOverlapCartonTemplate,
        new SleeveBoxTemplate,
        new SnapLockBottomTemplate,
    ];

    foreach ($templates as $template) {
        $dimensionSets = [
            $template->defaults(),
            [...$template->defaults(), 'dust_flap' => 180, 'tuck_flap' => 35, 'tuck_radius' => 8, 'lock_tab' => 20, 'corner_tab' => 100],
        ];

        foreach ($dimensionSets as $dimensions) {
            $geometry = $template->generate($dimensions);
            $bounds = $geometry['bounds'];
            $maximumX = $bounds['x'] + $bounds['width'];
            $maximumY = $bounds['y'] + $bounds['height'];

            expect($geometry['layers'])
                ->toHaveKeys(['cut', 'crease', 'glue', 'bleed'])
                ->and($geometry['layers']['cut'])->not->toBeEmpty()
                ->and($geometry['layers']['crease'])->not->toBeEmpty();

            foreach (['cut', 'crease'] as $layer) {
                foreach ($geometry['layers'][$layer] as $line) {
                    expect($line['x1'])->toBeGreaterThanOrEqual($bounds['x'] - 0.000001)
                        ->and($line['x1'])->toBeLessThanOrEqual($maximumX + 0.000001)
                        ->and($line['x2'])->toBeGreaterThanOrEqual($bounds['x'] - 0.000001)
                        ->and($line['x2'])->toBeLessThanOrEqual($maximumX + 0.000001)
                        ->and($line['y1'])->toBeGreaterThanOrEqual($bounds['y'] - 0.000001)
                        ->and($line['y1'])->toBeLessThanOrEqual($maximumY + 0.000001)
                        ->and($line['y2'])->toBeGreaterThanOrEqual($bounds['y'] - 0.000001)
                        ->and($line['y2'])->toBeLessThanOrEqual($maximumY + 0.000001);
                }
            }

            foreach ($geometry['layers']['glue'] as $area) {
                foreach ($area['points'] as $point) {
                    expect($point['x'])->toBeGreaterThanOrEqual($bounds['x'] - 0.000001)
                        ->and($point['x'])->toBeLessThanOrEqual($maximumX + 0.000001)
                        ->and($point['y'])->toBeGreaterThanOrEqual($bounds['y'] - 0.000001)
                        ->and($point['y'])->toBeLessThanOrEqual($maximumY + 0.000001);
                }
            }
        }
    }
});

it('defines templates with shared base dimensions', function (): void {
    foreach ([new ReverseTuckFlapBoxTemplate, new StraightTuckFlapTemplate] as $template) {
        expect($template->key())->not->toBeEmpty();
        expect($template->defaults())
            ->toHaveKeys(['l', 'w', 'h']);
    }
});

it('generates reverse tuck closures on opposite faces with source reliefs', function (): void {
    $template = new ReverseTuckFlapBoxTemplate;
    $geometry = $template->generate(['l' => 160, 'w' => 55, 'h' => 120, 'tuck_flap' => 15, 'glue_flap' => 20]);
    expect($geometry['layers']['crease'])->toContain(['x1' => 215.0, 'y1' => -120.5, 'x2' => 375.0, 'y2' => -120.5])
        ->toContain(['x1' => 160.0, 'y1' => .5, 'x2' => 0.0, 'y2' => .5])
        ->and(array_column($template->advancedFields(), 'key'))->toBe(['tuck_flap', 'glue_flap']);
});

it('generates straight tuck closures on the same face', function (): void {
    $geometry = (new StraightTuckFlapTemplate)->generate(['l' => 160, 'w' => 55, 'h' => 120, 'tuck_flap' => 15, 'glue_flap' => 20]);
    expect($geometry['layers']['crease'])->toContain(['x1' => 160.0, 'y1' => -120.5, 'x2' => 0.0, 'y2' => -120.5])
        ->toContain(['x1' => 160.0, 'y1' => .5, 'x2' => 0.0, 'y2' => .5]);
});
it('builds reusable dieline helper geometry for directional flaps and areas', function (): void {
    $helpers = dielineGeometryHelpers();

    expect($helpers->tuck(10, 20, 30, 5, 'top'))->toEqual([
        ['x1' => 10.0, 'y1' => 20.0, 'x2' => 10.0, 'y2' => 15.0],
        ['x1' => 10.0, 'y1' => 15.0, 'x2' => 40.0, 'y2' => 15.0],
        ['x1' => 40.0, 'y1' => 15.0, 'x2' => 40.0, 'y2' => 20.0],
    ])
        ->and($helpers->dust(10, 20, 30, 5, 'bottom'))->toEqual([
            'cut' => [
                ['x1' => 10.0, 'y1' => 20.0, 'x2' => 10.75, 'y2' => 20.75],
                ['x1' => 10.75, 'y1' => 20.75, 'x2' => 12.0, 'y2' => 25.0],
                ['x1' => 12.0, 'y1' => 25.0, 'x2' => 38.0, 'y2' => 25.0],
                ['x1' => 38.0, 'y1' => 25.0, 'x2' => 39.25, 'y2' => 21.25],
                ['x1' => 39.25, 'y1' => 21.25, 'x2' => 40.0, 'y2' => 20.75],
                ['x1' => 40.0, 'y1' => 20.75, 'x2' => 40.0, 'y2' => 20.0],
            ],
            'crease' => [
                ['x1' => 40.0, 'y1' => 20.0, 'x2' => 10.0, 'y2' => 20.0],
            ],
        ])
        ->and($helpers->locking(10, 20, 30, 5, 'left'))->toEqual([
            ['x1' => 10.0, 'y1' => 20.0, 'x2' => 5.0, 'y2' => 20.0],
            ['x1' => 5.0, 'y1' => 20.0, 'x2' => 5.0, 'y2' => 50.0],
            ['x1' => 5.0, 'y1' => 50.0, 'x2' => 10.0, 'y2' => 50.0],
        ])
        ->and($helpers->locking(10, 20, 30, 5, 'right'))->toEqual([
            ['x1' => 10.0, 'y1' => 20.0, 'x2' => 15.0, 'y2' => 20.0],
            ['x1' => 15.0, 'y1' => 20.0, 'x2' => 15.0, 'y2' => 50.0],
            ['x1' => 15.0, 'y1' => 50.0, 'x2' => 10.0, 'y2' => 50.0],
        ])
        ->and($helpers->bleed(10, 20, 30, 40, 3))->toHaveCount(4)
        ->and($helpers->glue(10, 20, 30, 40))->toHaveKey('points');
});

it('builds a profiled dust flap with angled shoulders and a closed baseline', function (): void {
    $helpers = dielineGeometryHelpers();

    expect($helpers->profiledDust(0, 0, 100, 60, 'top'))->toEqual([
        'cut' => [
            ['x1' => 0.0, 'y1' => 0.0, 'x2' => 8.0, 'y2' => -8.0],
            ['x1' => 8.0, 'y1' => -8.0, 'x2' => 10.0, 'y2' => -60.0],
            ['x1' => 10.0, 'y1' => -60.0, 'x2' => 90.0, 'y2' => -60.0],
            ['x1' => 90.0, 'y1' => -60.0, 'x2' => 94.0, 'y2' => -20.0],
            ['x1' => 94.0, 'y1' => -20.0, 'x2' => 100.0, 'y2' => -10.0],
            ['x1' => 100.0, 'y1' => -10.0, 'x2' => 100.0, 'y2' => 0.0],
        ],
        'crease' => [
            ['x1' => 100.0, 'y1' => 0.0, 'x2' => 0.0, 'y2' => 0.0],
        ],
    ]);
});

it('builds a tuck flap with a closure panel, small slits, and separated crease lines', function (): void {
    $tuck = dielineGeometryHelpers()->tuckComponent(
        x: 0,
        y: 0,
        span: 100,
        closureHeight: 100,
        direction: 'top',
        tuckHeight: 15,
        radius: 6,
        slitWidth: 5,
        slitHeight: 2,
        arcSegments: 2,
    );

    expect($tuck['cut'])
        ->toContain(['x1' => 0.0, 'y1' => 1.0, 'x2' => 0.0, 'y2' => 101.0])
        ->toContain(['x1' => 100.0, 'y1' => 1.0, 'x2' => 100.0, 'y2' => 101.0])
        ->toContain(['x1' => 0.0, 'y1' => 1.0, 'x2' => 5.0, 'y2' => 1.0])
        ->toContain(['x1' => 5.0, 'y1' => 1.0, 'x2' => 5.0, 'y2' => 3.0]);

    expect($tuck['crease'])->toEqual([
        ['x1' => 5.0, 'y1' => 2.0, 'x2' => 95.0, 'y2' => 2.0],
        ['x1' => 100.0, 'y1' => 101.0, 'x2' => 0.0, 'y2' => 101.0],
    ]);
});

it('keeps a 50 millimetre closure and 15 millimetre tuck flap exact', function (): void {
    $tuck = dielineGeometryHelpers()->tuckComponent(
        x: 0,
        y: 0,
        span: 100,
        closureHeight: 50,
        direction: 'top',
        tuckHeight: 15,
        radius: 6,
        slitWidth: 5,
        slitHeight: 2,
        arcSegments: 2,
    );

    expect($tuck['cut'])
        ->toContain(['x1' => 0.0, 'y1' => 1.0, 'x2' => 0.0, 'y2' => 51.0])
        ->toContain(['x1' => 5.0, 'y1' => 1.0, 'x2' => 5.0, 'y2' => 3.0]);

    $minimumY = collect($tuck['cut'])
        ->flatMap(fn (array $line): array => [$line['y1'], $line['y2']])
        ->min();

    expect($minimumY)->toBe(-14.0);

    expect($tuck['crease'])
        ->toContain(['x1' => 5.0, 'y1' => 2.0, 'x2' => 95.0, 'y2' => 2.0])
        ->toContain(['x1' => 100.0, 'y1' => 51.0, 'x2' => 0.0, 'y2' => 51.0]);
});

it('builds a tapered glue flap with a hinge crease and glue area', function (): void {
    expect(dielineGeometryHelpers()->glueFlap(100, 10, 20, 100, 'left', 10, 10))->toEqual([
        'cut' => [
            ['x1' => 100.0, 'y1' => 10.0, 'x2' => 80.0, 'y2' => 20.0],
            ['x1' => 80.0, 'y1' => 20.0, 'x2' => 80.0, 'y2' => 100.0],
            ['x1' => 80.0, 'y1' => 100.0, 'x2' => 100.0, 'y2' => 110.0],
        ],
        'crease' => [
            ['x1' => 100.0, 'y1' => 10.0, 'x2' => 100.0, 'y2' => 110.0],
        ],
        'glue' => [
            [
                'points' => [
                    ['x' => 100.0, 'y' => 10.0],
                    ['x' => 80.0, 'y' => 20.0],
                    ['x' => 80.0, 'y' => 100.0],
                    ['x' => 100.0, 'y' => 110.0],
                ],
            ],
        ],
    ]);
});

it('renders dieline cut and crease strokes at 1.5 millimetres', function (): void {
    $svg = (new SvgDielineRenderer)->render([
        'name' => 'Stroke test',
        'bounds' => ['width' => 10, 'height' => 10],
        'layers' => [
            'cut' => [['x1' => 0.0, 'y1' => 0.0, 'x2' => 10.0, 'y2' => 0.0]],
            'crease' => [['x1' => 0.0, 'y1' => 1.0, 'x2' => 10.0, 'y2' => 1.0]],
            'glue' => [],
            'bleed' => [],
        ],
    ]);

    expect($svg)
        ->toContain('stroke-width="1.5"')
        ->not->toContain('stroke-width="1.8"');
});

it('renders print-ready SVG and PDF exports with exact physical styles', function (): void {
    $geometry = [
        'name' => 'Print test',
        'bounds' => ['width' => 10, 'height' => 10],
        'layers' => [
            'cut' => [['x1' => 0.0, 'y1' => 0.0, 'x2' => 10.0, 'y2' => 0.0]],
            'crease' => [['x1' => 0.0, 'y1' => 1.0, 'x2' => 10.0, 'y2' => 1.0]],
            'glue' => [['points' => [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 10.0, 'y' => 0.0],
                ['x' => 10.0, 'y' => 10.0],
            ]]],
            'bleed' => [['x1' => -1.0, 'y1' => -1.0, 'x2' => 11.0, 'y2' => -1.0]],
        ],
        'labels' => [['x' => 5.0, 'y' => 5.0, 'text' => 'Width']],
    ];

    $svg = (new SvgDielineRenderer)->renderForSvg($geometry);
    $pdfSvg = (new SvgDielineRenderer)->renderForPdf($geometry);

    expect($svg)
        ->toContain('width="10mm"')
        ->toContain('height="10mm"')
        ->toContain('stroke-width="0.25pt"')
        ->toContain('stroke="#ff0000"')
        ->toContain('stroke="#000000"')
        ->toContain('stroke="#ff00ff"')
        ->not->toContain('<text')
        ->not->toContain('stroke-dasharray')
        ->not->toContain('<polygon')
        ->not->toContain('fill="#dcfce7"');

    expect($pdfSvg)
        ->toContain('width="10mm"')
        ->toContain('height="10mm"')
        ->toContain('stroke-width="0.333333"')
        ->toContain('x2="37.795"')
        ->not->toContain('<text')
        ->not->toContain('stroke-dasharray')
        ->not->toContain('<polygon')
        ->not->toContain('fill="#dcfce7"');
});

it('renders DXF exports with solid print layers and print lineweight', function (): void {
    $dxf = (new DxfDielineRenderer)->render([
        'layers' => [
            'cut' => [],
            'crease' => [],
            'glue' => [['points' => [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 10.0, 'y' => 0.0],
            ]]],
            'bleed' => [],
        ],
        'labels' => [['x' => 5.0, 'y' => 5.0, 'text' => 'Width']],
    ]);

    expect($dxf)
        ->toContain("2\r\nCUT\r\n70\r\n0\r\n62\r\n1\r\n6\r\nCONTINUOUS\r\n370\r\n9")
        ->toContain("2\r\nCREASE\r\n70\r\n0\r\n62\r\n7\r\n6\r\nCONTINUOUS\r\n370\r\n9")
        ->toContain("2\r\nBLEED\r\n70\r\n0\r\n62\r\n6\r\n6\r\nCONTINUOUS\r\n370\r\n9")
        ->not->toContain("2\r\nGLUE\r\n")
        ->not->toContain('Width');
});

it('draws four separate snap-lock base hinges and the source locking profiles', function (): void {
    $geometry = (new SnapLockBottomTemplate)->generate(['l' => 100, 'w' => 50, 'h' => 150, 'tuck_flap' => 15, 'glue_flap' => 13]);
    $base = array_filter($geometry['layers']['crease'], fn (array $line): bool => abs($line['y1']) < .00001 && abs($line['y2']) < .00001);
    expect($base)->toHaveCount(4)->and($geometry['bounds']['y'])->toBe(-214.0)
        ->and($geometry['bounds']['y'] + $geometry['bounds']['height'])->toEqualWithDelta(37.5, .00001);
});

it('draws the source crash-lock diagonal wings and three gluing contact areas', function (): void {
    $geometry = (new AutoBottomTuckTopTemplate)->generate(['l' => 100, 'w' => 50, 'h' => 150, 'tuck_flap' => 15, 'glue_flap' => 13]);
    $diagonals = array_filter($geometry['layers']['crease'], fn (array $line): bool => abs(abs($line['x2'] - $line['x1']) - abs($line['y2'] - $line['y1'])) < .00001 && abs($line['x2'] - $line['x1']) > 10);
    expect($diagonals)->toHaveCount(2)->and($geometry['layers']['glue'])->toHaveCount(3);
});

it('uses full-width overlap flaps and adjusts their depth independently', function (): void {
    $template = new FullOverlapCartonTemplate;
    $dimensions = ['l' => 100, 'w' => 50, 'h' => 150, 'glue_flap' => 20, 'flap_height' => 49.1];
    $geometry = $template->generate($dimensions);
    expect($geometry['bounds']['x'])->toBe(-20.0)->and($geometry['bounds']['width'])->toEqualWithDelta(319.1, .00001)
        ->and($geometry['bounds']['height'])->toEqualWithDelta(248.2, .00001);
    $changed = $template->generate([...$dimensions, 'flap_height' => 35]);
    expect($changed['bounds']['height'])->toEqualWithDelta(220, .00001)->and($changed['layers']['glue'])->toHaveCount(1);
});

it('builds a calibrated sleeve and a separate one-piece hinged food tray', function (): void {
    $sleeve = (new SleeveBoxTemplate)->generate([]);
    $tray = (new FoodTrayTemplate)->generate(['l' => 150, 'w' => 90, 'h' => 40, 'tuck_flap' => 15, 'corner_tab' => 20]);
    expect($sleeve['bounds']['height'])->toBe(50.0)->and($sleeve['layers']['glue'])->toHaveCount(1)
        ->and($sleeve['layers']['crease'])->toHaveCount(4)
        ->and($tray['bounds'])->toBe(['x' => -60.0, 'y' => -235.0, 'width' => 270.0, 'height' => 275.0])
        ->and($tray['layers']['crease'])->toContain(['x1' => 149.5, 'y1' => -130.5, 'x2' => .5, 'y2' => -130.5]);
});
it('supports canvas-style drawing commands for dieline layers', function (): void {
    $ctx = new DielineCanvas(arcSegments: 2);

    $ctx->save();
    $ctx->translate(10, 20);
    $ctx->beginPath();
    $ctx->moveTo(0, 0);
    $ctx->lineTo(0, -20);
    $ctx->arcTo(0, -30, 10, -30, 10);
    $ctx->strokeStyle = 'cut';
    $ctx->stroke();
    $ctx->restore();

    $ctx->beginPath();
    $ctx->moveTo(10, 20);
    $ctx->lineTo(40, 20);
    $ctx->strokeStyle = 'crease';
    $ctx->stroke();

    expect($ctx->layers()['cut'])->not->toBeEmpty()
        ->and($ctx->layers()['cut'][0])->toBe(['x1' => 10.0, 'y1' => 20.0, 'x2' => 10.0, 'y2' => 0.0])
        ->and($ctx->layers()['crease'])->toBe([
            ['x1' => 10.0, 'y1' => 20.0, 'x2' => 40.0, 'y2' => 20.0],
        ]);
});

it('fits an oversized corner radius to both available edges in both drawing helpers', function (): void {
    $path = new DielinePath;
    $path->moveTo(0, 3)->arcTo(0, 5, 5, 5, 20)->lineTo(5, 5);
    $canvas = new DielineCanvas;
    $canvas->beginPath();
    $canvas->moveTo(0, 3);
    $canvas->arcTo(0, 5, 5, 5, 20);
    $canvas->lineTo(5, 5);
    $canvas->strokeStyle = 'cut';
    $canvas->stroke();

    foreach ([$path->lines(), $canvas->layers()['cut']] as $lines) {
        expect($lines[array_key_last($lines)])
            ->toEqual(['x1' => 2.0, 'y1' => 5.0, 'x2' => 5.0, 'y2' => 5.0]);

        foreach ($lines as $line) {
            expect($line['x1'])->toBeBetween(-0.000001, 5.000001)
                ->and($line['y1'])->toBeBetween(2.999999, 5.000001);
        }
    }
});

it('keeps the chord error of a large rounded corner below one hundredth of a millimetre', function (): void {
    $path = new DielinePath;
    $path->moveTo(0, 100)->arcTo(0, 0, 100, 0, 100);

    foreach ($path->lines() as $line) {
        $x = ($line['x1'] + $line['x2']) / 2;
        $y = ($line['y1'] + $line['y2']) / 2;
        expect(abs(hypot($x - 100, $y - 100) - 100))->toBeLessThanOrEqual(0.010001);
    }
});

it('exports large physical dimensions without locale grouping separators', function (): void {
    $geometry = ['name' => 'Large bag', 'bounds' => ['x' => -1000, 'y' => -500, 'width' => 2400, 'height' => 1500], 'layers' => ['cut' => [['x1' => -1000, 'y1' => -500, 'x2' => 1400, 'y2' => 1000]]]];
    $svg = (new SvgDielineRenderer)->renderForSvg($geometry);
    $xml = simplexml_load_string($svg);

    expect((string) $xml['viewBox'])->toBe('-1000 -500 2400 1500')
        ->and((string) $xml['width'])->toBe('2400mm')
        ->and((string) $xml['height'])->toBe('1500mm')
        ->and((new SvgDielineRenderer)->renderForPdf($geometry))->not->toContain(',');
});

it('shows only used legend layers and gives each one an accurate visual swatch', function (): void {
    $line = ['x1' => 0.0, 'y1' => 0.0, 'x2' => 10.0, 'y2' => 10.0];
    $geometry = ['name' => 'Legend sample', 'bounds' => ['width' => 10, 'height' => 10], 'layers' => ['cut' => [$line], 'crease' => [$line], 'glue' => [], 'bleed' => [], 'perforation' => [], 'cut_crease' => []]];
    $renderer = new SvgDielineRenderer;
    $preview = (string) $renderer->preview($geometry);
    expect($preview)->toContain('data-dieline-layer="cut"', 'data-dieline-layer="crease"')
        ->not->toContain('data-dieline-layer="glue"', 'data-dieline-layer="bleed"', 'data-dieline-layer="perforation"', 'data-dieline-layer="cut_crease"');
    $geometry['layers'] = [...$geometry['layers'], 'glue' => [['points' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 10]]]], 'bleed' => [$line], 'perforation' => [$line], 'cut_crease' => [$line]];
    $document = new DOMDocument;
    $document->loadXML('<root>'.(string) $renderer->preview($geometry).'</root>');
    $xpath = new DOMXPath($document);
    foreach (['cut', 'crease', 'bleed', 'perforation', 'cut_crease'] as $layer) {
        expect($xpath->query('//*[@data-dieline-layer="'.$layer.'"]/*[local-name()="svg"]/*')->length)->toBe(1);
    }
    expect((string) $renderer->preview($geometry))->not->toContain('data-dieline-layer="glue"', '<polygon', '#dcfce7', '#16a34a');
    expect($xpath->query('//*[@data-dieline-layer="perforation"]//line')->item(0)->getAttribute('stroke-dasharray'))->toBe('2 2')
        ->and($xpath->query('//*[@data-dieline-layer="cut_crease"]//line')->item(0)->getAttribute('stroke-dasharray'))->toBe('8 3 2 3');
});

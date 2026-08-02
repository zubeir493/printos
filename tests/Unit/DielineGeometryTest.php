<?php

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Geometry\DielineCanvas;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use App\Services\Dielines\Templates\Fefco0210Template;
use App\Services\Dielines\Templates\Fefco0427Template;
use App\Services\Dielines\Templates\ReverseTuckFlapBoxTemplate;
use App\Services\Dielines\Templates\RoundedTuckCartonTemplate;

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

it('defines templates with shared base dimensions', function (): void {
    foreach ([new Fefco0210Template, new Fefco0427Template, new RoundedTuckCartonTemplate, new ReverseTuckFlapBoxTemplate] as $template) {
        expect($template->key())->not->toBeEmpty();
        expect($template->defaults())
            ->toHaveKeys(['l', 'w', 'h']);
    }
});

it('keeps tuck slit and glue taper dimensions as internal defaults', function (): void {
    $fields = collect((new RoundedTuckCartonTemplate)->advancedFields())->pluck('key')->all();

    expect($fields)
        ->not->toContain('tuck_slit_width')
        ->not->toContain('tuck_slit_height')
        ->not->toContain('glue_top_taper')
        ->not->toContain('glue_bottom_taper');

    expect((new RoundedTuckCartonTemplate)->defaults())
        ->toMatchArray([
            'tuck_flap' => 15,
            'dust_flap' => 100,
            'glue_flap' => 20,
        ]);

    expect(collect((new RoundedTuckCartonTemplate)->advancedFields())->keyBy('key')->all())
        ->toHaveKey('dust_flap');
});

it('derives the rounded dust flap default from half the length', function (): void {
    $registry = Mockery::mock(DielineTemplateRegistry::class);
    $registry->shouldReceive('template')
        ->with('rounded-tuck-carton')
        ->andReturn(new RoundedTuckCartonTemplate);

    $dimensions = (new DielineGeometryService($registry))->normalize('rounded-tuck-carton', [
        'l' => 160,
        'w' => 55,
        'h' => 120,
    ]);

    expect($dimensions['dust_flap'])->toBe(80.0);
});

it('generates fefco 0210 geometry with cut crease glue and bleed layers', function (): void {
    $geometry = (new Fefco0210Template)->generate([
        'l' => 160,
        'w' => 50,
        'h' => 90,
        'tuck_flap' => 28,
        'glue_flap' => 18,
        'dust_flap' => 25,
        'bleed' => 3,
        'board_thickness' => 1.5,
    ]);

    expect($geometry['bounds'])
        ->toBe(['width' => 438.0, 'height' => 146.0])
        ->and($geometry['layers']['cut'])->not->toBeEmpty()
        ->and($geometry['layers']['crease'])->not->toBeEmpty()
        ->and($geometry['layers']['glue'])->not->toBeEmpty()
        ->and($geometry['layers']['bleed'])->not->toBeEmpty();
});

it('generates fefco 0427 geometry without glue layers', function (): void {
    $geometry = (new Fefco0427Template)->generate([
        'l' => 220,
        'w' => 160,
        'h' => 45,
        'lid_tuck' => 35,
        'side_lock' => 28,
        'front_lock' => 24,
        'bleed' => 3,
        'board_thickness' => 1.5,
    ]);

    expect($geometry['bounds'])
        ->toBe(['width' => 366.0, 'height' => 514.0])
        ->and($geometry['layers']['cut'])->not->toBeEmpty()
        ->and($geometry['layers']['crease'])->not->toBeEmpty()
        ->and($geometry['layers']['glue'])->toBeEmpty();
});

it('generates reverse tuck flap box with reference flap directions', function (): void {
    $template = new ReverseTuckFlapBoxTemplate;
    $geometry = $template->generate([
        'l' => 160,
        'w' => 55,
        'h' => 120,
        'tuck_flap' => 15,
        'tuck_radius' => 6,
        'dust_flap' => 50,
        'glue_flap' => 20,
    ]);

    expect($template->name())->toBe('Reverse Tuck Flap Box')
        ->and($geometry['bounds'])
        ->toBe(['x' => 0.0, 'y' => -71.0, 'width' => 450.0, 'height' => 262.0])
        ->and($geometry['layers']['cut'])
        ->toContain(['x1' => 450.0, 'y1' => 0.0, 'x2' => 450.0, 'y2' => 120.0])
        ->toContain(['x1' => 235.0, 'y1' => -56.0, 'x2' => 235.0, 'y2' => 0.0])
        ->toContain(['x1' => 20.0, 'y1' => 176.0, 'x2' => 20.0, 'y2' => 120.0])
        ->toContain(['x1' => 20.0, 'y1' => 0.0, 'x2' => 180.0, 'y2' => 0.0])
        ->toContain(['x1' => 235.0, 'y1' => 120.0, 'x2' => 395.0, 'y2' => 120.0])
        ->toContain(['x1' => 235.0, 'y1' => 0.0, 'x2' => 232.25, 'y2' => -2.75])
        ->toContain(['x1' => 180.0, 'y1' => 120.0, 'x2' => 182.75, 'y2' => 122.75])
        ->toContain(['x1' => 395.0, 'y1' => 0.0, 'x2' => 397.75, 'y2' => -2.75])
        ->toContain(['x1' => 450.0, 'y1' => 120.0, 'x2' => 447.25, 'y2' => 122.75])
        ->and($geometry['layers']['crease'])
        ->toContain(['x1' => 395.0, 'y1' => 0.0, 'x2' => 235.0, 'y2' => 0.0])
        ->toContain(['x1' => 180.0, 'y1' => 0.0, 'x2' => 235.0, 'y2' => 0.0])
        ->toContain(['x1' => 180.0, 'y1' => 120.0, 'x2' => 20.0, 'y2' => 120.0])
        ->toContain(['x1' => 235.0, 'y1' => 120.0, 'x2' => 180.0, 'y2' => 120.0])
        ->and(collect($template->advancedFields())->pluck('key')->all())
        ->toBe(['tuck_flap', 'tuck_radius', 'dust_flap', 'glue_flap']);

    expect($geometry['layers']['crease'])
        ->not->toContain(['x1' => 20.0, 'y1' => 0.0, 'x2' => 180.0, 'y2' => 0.0])
        ->not->toContain(['x1' => 235.0, 'y1' => 120.0, 'x2' => 395.0, 'y2' => 120.0]);
});

it('generates rounded tuck carton geometry with rounded tuck flap cut and crease layers', function (): void {
    $geometry = (new RoundedTuckCartonTemplate)->generate([
        'l' => 160,
        'w' => 55,
        'h' => 120,
        'tuck_flap' => 15,
        'tuck_radius' => 6,
        'dust_flap' => 34,
        'glue_flap' => 20,
        'bleed' => 3,
        'board_thickness' => 1.5,
    ]);

    expect($geometry['bounds'])
        ->toBe(['x' => 0.0, 'y' => -56.0, 'width' => 355.0, 'height' => 336.0])
        ->and($geometry['layers']['cut'])
        ->toContain(['x1' => 195.0, 'y1' => -41.0, 'x2' => 195.0, 'y2' => 120.0])
        ->toContain(['x1' => 195.0, 'y1' => 120.0, 'x2' => 189.9, 'y2' => 114.9])
        ->toContain(['x1' => 35.0, 'y1' => 120.0, 'x2' => 15.0, 'y2' => 130.0])
        ->and($geometry['layers']['crease'])->toEqual([
            ['x1' => 200.0, 'y1' => -40.0, 'x2' => 350.0, 'y2' => -40.0],
            ['x1' => 355.0, 'y1' => 120.0, 'x2' => 195.0, 'y2' => 120.0],
            ['x1' => 35.0, 'y1' => 120.0, 'x2' => 195.0, 'y2' => 120.0],
            ['x1' => 35.0, 'y1' => 120.0, 'x2' => 35.0, 'y2' => 280.0],
        ])
        ->and($geometry['layers']['glue'])->toEqual([
            [
                'points' => [
                    ['x' => 35.0, 'y' => 120.0],
                    ['x' => 15.0, 'y' => 130.0],
                    ['x' => 15.0, 'y' => 270.0],
                    ['x' => 35.0, 'y' => 280.0],
                ],
            ],
        ])
        ->and(collect($geometry['labels'])->pluck('text')->all())
        ->not->toContain('Glue')
        ->and($geometry['labels'])->toHaveCount(3);
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
        ->toContain(['x1' => 0.0, 'y1' => 0.0, 'x2' => 0.0, 'y2' => 101.0])
        ->toContain(['x1' => 100.0, 'y1' => 0.0, 'x2' => 100.0, 'y2' => 101.0])
        ->toContain(['x1' => 0.0, 'y1' => 0.0, 'x2' => 5.0, 'y2' => 0.0])
        ->toContain(['x1' => 5.0, 'y1' => 0.0, 'x2' => 5.0, 'y2' => 2.0]);

    expect($tuck['crease'])->toEqual([
        ['x1' => 5.0, 'y1' => 1.0, 'x2' => 95.0, 'y2' => 1.0],
        ['x1' => 100.0, 'y1' => 101.0, 'x2' => 0.0, 'y2' => 101.0],
    ]);
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

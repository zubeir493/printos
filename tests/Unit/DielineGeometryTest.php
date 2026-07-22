<?php

use App\Services\Dielines\Concerns\BuildsDielineGeometry;
use App\Services\Dielines\Geometry\DielineCanvas;
use App\Services\Dielines\Templates\Fefco0210Template;
use App\Services\Dielines\Templates\Fefco0427Template;
use App\Services\Dielines\Templates\RoundedTuckCartonTemplate;

function dielineGeometryHelpers(): object
{
    return new class
    {
        use BuildsDielineGeometry;

        public function tuck(float $x, float $y, float $span, float $depth, string $direction): array
        {
            return $this->tuckFlap($x, $y, $span, $depth, $direction);
        }

        public function roundedTuck(
            float $x,
            float $y,
            float $span,
            float $depth,
            string $direction,
            float $radius,
            float $slitLength,
            float $slitOffset,
            int $arcSegments,
        ): array {
            return $this->roundedTuckFlap($x, $y, $span, $depth, $direction, $radius, $slitLength, $slitOffset, $arcSegments);
        }

        public function dust(float $x, float $y, float $span, float $depth, string $direction): array
        {
            return $this->dustFlap($x, $y, $span, $depth, $direction);
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

it('defines templates with shared base dimensions', function (): void {
    foreach ([new Fefco0210Template, new Fefco0427Template, new RoundedTuckCartonTemplate] as $template) {
        expect($template->key())->not->toBeEmpty();
        expect($template->defaults())
            ->toHaveKeys(['l', 'w', 'h']);
    }
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

it('generates rounded tuck carton geometry with rounded tuck flap cut and crease layers', function (): void {
    $geometry = (new RoundedTuckCartonTemplate)->generate([
        'l' => 160,
        'w' => 55,
        'h' => 120,
        'tuck_flap' => 42,
        'tuck_radius' => 12,
        'tuck_slit' => 25,
        'tuck_slit_offset' => 5,
        'dust_flap' => 34,
        'glue_flap' => 20,
        'bleed' => 3,
        'board_thickness' => 1.5,
    ]);

    expect($geometry['bounds'])
        ->toBe(['width' => 355.0, 'height' => 145.0])
        ->and($geometry['layers']['cut'])->toHaveCount(25)
        ->and($geometry['layers']['crease'])->toHaveCount(1)
        ->and($geometry['layers']['glue'])->toBeEmpty()
        ->and($geometry['labels'])->toHaveCount(1);
});

it('builds reusable dieline helper geometry for directional flaps and areas', function (): void {
    $helpers = dielineGeometryHelpers();

    expect($helpers->tuck(10, 20, 30, 5, 'top'))->toEqual([
        ['x1' => 10.0, 'y1' => 20.0, 'x2' => 10.0, 'y2' => 15.0],
        ['x1' => 10.0, 'y1' => 15.0, 'x2' => 40.0, 'y2' => 15.0],
        ['x1' => 40.0, 'y1' => 15.0, 'x2' => 40.0, 'y2' => 20.0],
    ])
        ->and($helpers->dust(10, 20, 30, 5, 'bottom'))->toEqual([
            ['x1' => 10.0, 'y1' => 20.0, 'x2' => 10.0, 'y2' => 25.0],
            ['x1' => 10.0, 'y1' => 25.0, 'x2' => 40.0, 'y2' => 25.0],
            ['x1' => 40.0, 'y1' => 25.0, 'x2' => 40.0, 'y2' => 20.0],
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

it('converts canvas-style rounded tuck flaps into cut and crease geometry', function (): void {
    $roundedTuck = dielineGeometryHelpers()->roundedTuck(
        x: 0,
        y: 0,
        span: 100,
        depth: 30,
        direction: 'top',
        radius: 10,
        slitLength: 25,
        slitOffset: 5,
        arcSegments: 2,
    );

    expect($roundedTuck['cut'])->toHaveCount(11)
        ->and($roundedTuck['cut'][0])->toEqual(['x1' => 0.0, 'y1' => 0.0, 'x2' => 0.0, 'y2' => -20.0])
        ->and($roundedTuck['cut'][7])->toEqual(['x1' => 0.0, 'y1' => -25.0, 'x2' => 25.0, 'y2' => -25.0])
        ->and($roundedTuck['cut'][9])->toEqual(['x1' => 100.0, 'y1' => -25.0, 'x2' => 75.0, 'y2' => -25.0])
        ->and($roundedTuck['crease'])->toEqual([
            ['x1' => 25.0, 'y1' => -20.0, 'x2' => 75.0, 'y2' => -20.0],
        ]);
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

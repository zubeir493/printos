<?php

use App\Services\Dielines\Templates\Fefco0210Template;
use App\Services\Dielines\Templates\Fefco0427Template;

it('defines fefco 0210 and 0427 templates with shared base dimensions', function (): void {
    foreach ([new Fefco0210Template, new Fefco0427Template] as $template) {
        expect($template->key())->toStartWith('fefco-');
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

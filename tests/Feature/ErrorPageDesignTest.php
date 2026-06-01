<?php

test('server error view uses the simplified design', function (): void {
    $this->withoutVite();

    $html = view('errors.500')->render();

    expect($html)
        ->toContain('Dashboard')
        ->toContain('Something went wrong')
        ->not->toContain('Go back')
        ->not->toContain('images/logo.svg')
        ->and(substr_count($html, 'Dashboard'))->toBe(1);
});

test('emergency public fallback uses the simplified design', function (): void {
    $source = file_get_contents(public_path('index.php'));

    expect($source)
        ->toContain('$GLOBALS[\'printos_request_completed\'] = false;')
        ->toContain('$GLOBALS[\'printos_request_completed\'] = true;')
        ->toContain('<a href="/" class="btn-home">Dashboard</a>')
        ->not->toContain('btn-back')
        ->not->toContain('Go back')
        ->not->toContain('images/logo.svg')
        ->and(substr_count($source, 'Dashboard'))->toBe(1);
});

<?php

use Filament\Facades\Filament;

it('uses the new favicon across every panel', function () {
    foreach (Filament::getPanels() as $panel) {
        expect($panel->getFavicon())->toBe(asset('images/favicon.svg'));
    }
});

it('exposes the favicon as the PWA icon', function () {
    $manifest = json_decode(
        file_get_contents(public_path('manifest.webmanifest')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['icons'])->toContain([
        'src' => '/images/favicon.svg',
        'sizes' => 'any',
        'type' => 'image/svg+xml',
        'purpose' => 'any',
    ]);
});

it('links the manifest and favicon from the panel head', function () {
    $this->get(route('filament.admin.auth.login'))
        ->assertSuccessful()
        ->assertSee(asset('manifest.webmanifest'), escape: false)
        ->assertSee(asset('images/favicon.svg'), escape: false);
});

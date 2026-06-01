<?php

use Illuminate\Support\Facades\Route;

it('renders a single 500 page for filament full-page errors', function (): void {
    Route::get('/filament/test-error', fn () => throw new RuntimeException('boom'));

    $response = $this->get('/filament/test-error');

    $response->assertStatus(500)
        ->assertSee('Something went wrong');
});

it('returns a json error for filament ajax or livewire requests', function (): void {
    Route::get('/filament/test-error', fn () => throw new RuntimeException('boom'));

    $response = $this->withHeaders([
        'X-Livewire' => '1',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/filament/test-error');

    $response->assertStatus(500)
        ->assertJson([
            'message' => 'An unexpected error occurred. Please try again or contact your administrator if it keeps happening.',
        ]);
});

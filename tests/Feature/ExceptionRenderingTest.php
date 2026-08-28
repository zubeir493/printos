<?php

use Filament\Notifications\Livewire\Notifications;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Support\ExceptionRenderingTestComponent;

it('redirects logged-out panel users to login', function (): void {
    $this->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect(route('filament.admin.auth.login'));
});

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

it('recognises named filament routes without a filament url prefix', function (): void {
    Route::get('/test-named-filament-error', fn () => throw new RuntimeException('boom'))
        ->name('filament.admin.pages.test-error');

    $this->get('/test-named-filament-error')
        ->assertStatus(500)
        ->assertSee('Something went wrong');
});

it('uses the managed error page for non filament server errors', function (): void {
    Route::get('/test-managed-error', fn () => throw new RuntimeException('boom'));

    $this->get('/test-managed-error')
        ->assertStatus(500)
        ->assertSee('Something went wrong');
});

it('turns livewire domain exceptions into actionable notifications', function (): void {
    Livewire::test(ExceptionRenderingTestComponent::class)
        ->call('fail')
        ->assertNotified('Something went wrong');
});

it('turns stale livewire actions into a record not found notification', function (): void {
    Livewire::test(ExceptionRenderingTestComponent::class)
        ->call('missing')
        ->assertNotified('Record not found');
});

it('turns offline private storage upload failures into actionable notifications', function (): void {
    Livewire::test(ExceptionRenderingTestComponent::class)
        ->call('storageOffline');

    $notifications = new Notifications;
    $notifications->mount();

    $notification = $notifications->notifications
        ->first(fn ($notification): bool => $notification->getTitle() === 'File upload failed');

    $body = (string) $notification?->getBody();

    expect($notification)->not->toBeNull()
        ->and(str_contains($body, 'storage service is unreachable'))->toBeTrue()
        ->and(str_contains($body, 'switch private uploads to local storage'))->toBeTrue();
});

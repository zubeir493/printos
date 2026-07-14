<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can store a web push subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('webpush.subscriptions.store'), [
            'endpoint' => 'https://example.com/push/subscription',
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
            'contentEncoding' => 'aes128gcm',
        ])
        ->assertSuccessful()
        ->assertJson([
            'subscribed' => true,
        ]);

    $subscriptions = $user->fresh()->pushSubscriptions;

    expect($subscriptions)->toHaveCount(1);
    expect($subscriptions->first()->endpoint)->toBe('https://example.com/push/subscription');
});

test('authenticated users can delete a web push subscription', function () {
    $user = User::factory()->create();

    $user->updatePushSubscription(
        endpoint: 'https://example.com/push/subscription',
        key: 'public-key',
        token: 'auth-token',
        contentEncoding: 'aes128gcm',
    );

    $this->actingAs($user)
        ->deleteJson(route('webpush.subscriptions.destroy'), [
            'endpoint' => 'https://example.com/push/subscription',
        ])
        ->assertSuccessful()
        ->assertJson([
            'subscribed' => false,
        ]);

    expect($user->fresh()->pushSubscriptions)->toHaveCount(0);
});

test('guests cannot store web push subscriptions', function () {
    $this->postJson(route('webpush.subscriptions.store'), [
        'endpoint' => 'https://example.com/push/subscription',
        'keys' => [
            'p256dh' => 'public-key',
            'auth' => 'auth-token',
        ],
    ])->assertUnauthorized();
});

test('profile page no longer renders browser notification toggle', function () {
    config()->set('webpush.vapid.public_key', 'test-public-key');

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.auth.profile'))
        ->assertSuccessful()
        ->assertDontSee('Enable push notifications?')
        ->assertDontSee('fi-toggle', false)
        ->assertDontSee("status.permission === 'denied'", false)
        ->assertSee('form.email', false)
        ->assertDontSee('form.name', false)
        ->assertDontSee('fi-simple-layout-header', false);
});

test('panel pages prompt users to enable browser notifications when permission is missing', function () {
    config()->set('webpush.vapid.public_key', 'test-public-key');

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->withSession(['show_webpush_permission_prompt' => true])
        ->get(route('filament.admin.auth.profile'))
        ->assertSuccessful()
        ->assertSee('const shouldShowPermissionAlert = true;', false)
        ->assertSee('Turn on browser notifications')
        ->assertSee('canShowPermissionAlert')
        ->assertSee('runAfterUiReady(showPermissionAlert)')
        ->assertSee('DOMContentLoaded')
        ->assertSee('packledge-webpush-enable')
        ->assertSee('subscribe({ requestBrowserPermission: false })', false)
        ->assertDontSee('sessionStorage', false)
        ->assertDontSee('permission-alert-shown', false)
        ->assertDontSee('unsubscribe', false);
});

test('panel pages do not prompt for browser notifications outside login', function () {
    config()->set('webpush.vapid.public_key', 'test-public-key');

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.auth.profile'))
        ->assertSuccessful()
        ->assertSee('const shouldShowPermissionAlert = false;', false);
});

test('login flashes browser notification prompt for the next request', function () {
    event(new Login('web', User::factory()->create(), false));

    expect(session('show_webpush_permission_prompt'))->toBeTrue();
});

<?php

use App\Models\User;
use App\UserRole;
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

test('profile page renders browser notification settings', function () {
    config()->set('webpush.vapid.public_key', 'test-public-key');

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.auth.profile'))
        ->assertSuccessful()
        ->assertSee('Enable push notifications?');
});

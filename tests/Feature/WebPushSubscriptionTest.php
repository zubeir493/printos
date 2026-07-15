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
        ->assertSee('form.email', false)
        ->assertDontSee('form.name', false)
        ->assertDontSee('fi-simple-layout-header', false);
});

test('authenticated panel pages register the web push notification prompt', function () {
    config()->set('webpush.vapid.public_key', 'test-public-key');

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->withSession(['show_webpush_permission_prompt' => true])
        ->get(route('filament.admin.pages.settings'))
        ->assertSuccessful()
        ->assertSee('const shouldShowPermissionAlert = true;', false)
        ->assertSee('Enable notifications to receive important alerts.')
        ->assertSee('Enable notifications for this site in your browser settings to receive important alerts.')
        ->assertSee('Enable notifications')
        ->assertSee('new window.FilamentNotification()', false)
        ->assertSee('new window.FilamentNotificationAction(\'enableNotifications\')', false)
        ->assertSee('[wire\\:key^="printos-webpush-permission-prompt.notifications."] .fi-no-notification-icon', false)
        ->assertSee('height: 2rem;', false)
        ->assertSee('! shouldShowPermissionAlert', false)
        ->assertSee('hasPromptNotificationBeenShown', false)
        ->assertSee('.dispatch(\'printos-webpush-enable-requested\')', false)
        ->assertSeeInOrder([
            "window.addEventListener('printos-webpush-enable-requested'",
            'isPromptNotificationDismissed = true;',
            'closePromptNotification();',
            'subscribe();',
        ], false)
        ->assertSeeInOrder([
            "window.localStorage.setItem(subscriptionStorageKey, 'true');",
            'closePromptNotification();',
            'subscribed: true,',
        ], false)
        ->assertSee('close-notification', false)
        ->assertDontSee(".icon('heroicon-o-bell-alert')\n                            .dispatch('printos-webpush-enable-requested')", false)
        ->assertDontSee('fi-callout', false)
        ->assertDontSee('rounded-lg border border-warning-200', false)
        ->assertDontSee('permission-alert-shown', false)
        ->assertDontSee('unsubscribe', false);
});

test('panel pages do not force the browser notification prompt outside login', function () {
    config()->set('webpush.vapid.public_key', 'test-public-key');

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.pages.settings'))
        ->assertSuccessful()
        ->assertSee('const shouldShowPermissionAlert = false;', false)
        ->assertSee('! shouldShowPermissionAlert', false)
        ->assertSee('hasPromptNotificationBeenShown', false);
});

test('login flashes browser notification prompt for the next request', function () {
    event(new Login('web', User::factory()->create(), false));

    expect(session('show_webpush_permission_prompt'))->toBeTrue();
});

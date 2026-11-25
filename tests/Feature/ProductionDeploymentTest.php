<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('the production administrator command creates a login without seed data', function (): void {
    $password = 'warehouse-demo-passphrase';
    $this->artisan('printos:create-admin')
        ->expectsQuestion('Name', 'Release Administrator')
        ->expectsQuestion('Email', 'ADMIN@example.com')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->assertSuccessful();

    $administrator = User::query()->where('email', 'admin@example.com')->sole();
    expect($administrator->role)->toBe(UserRole::Admin)
        ->and(Hash::check($password, $administrator->password))->toBeTrue();
});

test('the administrator command rejects existing emails and unattended execution', function (): void {
    $user = User::factory()->create(['role' => UserRole::Sales]);
    $this->artisan('printos:create-admin', ['--no-interaction' => true])->assertFailed();
    $this->artisan('printos:create-admin')
        ->expectsQuestion('Name', 'Different Name')
        ->expectsQuestion('Email', $user->email)
        ->expectsQuestion('Password', 'warehouse-demo-passphrase')
        ->expectsQuestion('Confirm password', 'warehouse-demo-passphrase')
        ->assertFailed();

    expect($user->fresh()->role)->toBe(UserRole::Sales);
    expect(User::query()->count())->toBe(1);
});

test('the administrator command validates the byte limit before hashing a password', function (): void {
    $password = str_repeat('ሀ', 25);
    $this->artisan('printos:create-admin')
        ->expectsQuestion('Name', 'Release Administrator')
        ->expectsQuestion('Email', 'admin@example.com')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->expectsOutputToContain('The password must be at most 72 bytes.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('the health probe accepts internal HTTP and HTTPS forwarding is limited to the configured proxy', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['trustedproxy.proxies' => '172.31.245.2']);

    $this->get('http://printos.test/up')->assertOk();
    $this->withHeader('X-Forwarded-Proto', 'https')
        ->withServerVariables(['REMOTE_ADDR' => '172.31.245.2'])
        ->get('http://printos.test/missing-proxy-probe')
        ->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '172.31.245.3'])
        ->get('http://printos.test/missing-proxy-probe')
        ->assertRedirect('https://printos.test/missing-proxy-probe');
});

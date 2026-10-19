<?php

use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\JobOrder;
use App\Models\User;
use App\Notifications\BankTransferStatusChangedNotification;
use App\Notifications\JobOrderCancelledNotification;
use App\UserRole;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Notification;

uses(DatabaseTruncation::class);

beforeEach(function (): void {
    RefreshDatabaseState::$migrated = false;
    $this->artisan('migrate:fresh');
    RefreshDatabaseState::$migrated = true;
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
    RefreshDatabaseState::$inMemoryConnections = [];
});

test('completed bank transfers notify finance operations and admin', function (): void {
    Notification::fake();

    $finance = User::factory()->create(['role' => UserRole::Finance]);
    User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['role' => UserRole::Operations]);
    $source = Bank::create([
        'name' => 'Source',
        'code' => 'SOURCE',
        'account_number' => fake()->numerify('##########'),
        'account_holder_name' => 'Packledge',
        'bank_name' => 'Source',
        'branch' => 'Main',
        'current_balance' => 500,
        'status' => 'active',
    ]);
    $destination = Bank::create([
        'name' => 'Destination',
        'code' => 'DESTINATION',
        'account_number' => fake()->numerify('##########'),
        'account_holder_name' => 'Packledge',
        'bank_name' => 'Destination',
        'branch' => 'Main',
        'current_balance' => 0,
        'status' => 'active',
    ]);
    $transfer = BankTransfer::create([
        'from_bank_id' => $source->id,
        'to_bank_id' => $destination->id,
        'amount' => 100,
        'transfer_date' => now(),
        'status' => 'pending',
    ]);

    $transfer->complete();

    Notification::assertSentTo($finance, BankTransferStatusChangedNotification::class);
});

test('cancelled production tasks notify production users', function (): void {
    Notification::fake();

    $production = User::factory()->create(['role' => UserRole::Production]);
    $task = JobOrder::factory()->create(['status' => 'design']);

    $task->update(['status' => 'cancelled']);

    Notification::assertSentTo($production, JobOrderCancelledNotification::class);
});

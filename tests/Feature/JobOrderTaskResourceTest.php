<?php

use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('job order tasks cannot be created from the standalone task resource', function () {
    expect(JobOrderTaskResource::canCreate())->toBeFalse();

    expect(Route::has('filament.admin.resources.job-order-tasks.create'))->toBeFalse();
    expect(Route::has('filament.design.resources.job-order-tasks.create'))->toBeFalse();
    expect(Route::has('filament.production.resources.job-order-tasks.create'))->toBeFalse();
    expect(Route::has('filament.operations.resources.job-order-tasks.create'))->toBeFalse();

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]))
        ->get('/job-order-tasks/create')
        ->assertNotFound();
});

<?php

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('single job orders replace standalone task routes', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    expect(JobOrderResource::canCreate())->toBeTrue();
    expect(Route::has('filament.admin.resources.job-orders.create'))->toBeTrue();
    expect(Route::has('filament.admin.resources.job-order-tasks.index'))->toBeFalse();
    $this->get('/job-order-tasks/create')->assertNotFound();
});

test('job order task global search eager loads result detail relations', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $designer = User::factory()->create([
        'role' => UserRole::Design,
        'name' => 'Search Designer',
    ]);

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $partner = Partner::create([
        'name' => 'Search Customer',
        'is_customer' => true,
    ]);

    $jobOrder = JobOrder::create([
        'name' => 'Printing', 'quantity' => 100,
        'job_order_number' => 'JO-SEARCH-001',
        'partner_id' => $partner->id,
        'job_type' => 'Book',
        'production_mode' => 'make_to_order',
        'services' => [],
        'submission_date' => now(),
        'status' => 'design',
    ]);

    tap($jobOrder, fn (JobOrder $record) => $record->updateQuietly([

        'designer_id' => $designer->id,
        'name' => 'Searchable Task',
        'quantity' => 1,
        'subtotal' => 0,
        'status' => 'design',
    ]));

    Model::preventLazyLoading();

    try {
        $results = JobOrderResource::getGlobalSearchResults('Searchable');
    } finally {
        Model::preventLazyLoading(false);
    }

    expect($results)->toHaveCount(1);
});

test('production queue lists only jobs that still need production', function () {
    Filament::setCurrentPanel(Filament::getPanel('production'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Production]));
    $active = JobOrder::factory()->create(['status' => 'production']);
    JobOrder::factory()->create(['status' => 'draft']);
    JobOrder::factory()->create(['status' => 'completed']);
    JobOrder::factory()->create(['status' => 'production', 'production_completed_at' => now()]);
    expect(JobOrderResource::getEloquentQuery()->pluck('id')->all())->toBe([$active->id]);
});

<?php

use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\Partner;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
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
        'job_order_number' => 'JO-SEARCH-001',
        'partner_id' => $partner->id,
        'job_type' => 'Book',
        'production_mode' => 'make_to_order',
        'services' => [],
        'submission_date' => now(),
        'status' => 'active',
    ]);

    JobOrderTask::create([
        'job_order_id' => $jobOrder->id,
        'designer_id' => $designer->id,
        'name' => 'Searchable Task',
        'quantity' => 1,
        'task_cost' => 0,
        'status' => 'design',
    ]);

    Model::preventLazyLoading();

    try {
        $results = JobOrderTaskResource::getGlobalSearchResults('Searchable');
    } finally {
        Model::preventLazyLoading(false);
    }

    expect($results)->toHaveCount(1);
});

test('job order task resource only lists tasks for active job orders', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $activeJobOrder = JobOrder::factory()->create(['status' => 'active']);
    $draftJobOrder = JobOrder::factory()->create(['status' => 'draft']);
    $completedJobOrder = JobOrder::factory()->create(['status' => 'completed']);

    $activeTask = JobOrderTask::factory()->create([
        'job_order_id' => $activeJobOrder->id,
        'name' => 'Visible active task',
    ]);

    JobOrderTask::factory()->create([
        'job_order_id' => $draftJobOrder->id,
        'name' => 'Hidden draft task',
    ]);

    JobOrderTask::factory()->create([
        'job_order_id' => $completedJobOrder->id,
        'name' => 'Hidden completed task',
    ]);

    $tasks = JobOrderTaskResource::getEloquentQuery()->pluck('id');

    expect($tasks->all())->toBe([$activeTask->id]);
});

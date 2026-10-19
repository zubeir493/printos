<?php

use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\User;
use App\Services\JobOrders\DuplicateJobOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('duplicates a job order with tasks as a fresh draft reorder', function (): void {
    $partner = Partner::factory()->create(['is_customer' => true]);
    $jobOrder = JobOrder::factory()->create([
        'job_order_number' => 'JO-OLD-001',
        'partner_id' => $partner->id,
        'production_mode' => 'make_to_order',
        'job_type' => 'packages',
        'services' => ['new_design' => true],
        'submission_date' => now()->subDays(10),
        'due_date' => now()->subDay(),
        'cost_calc_file' => 'job-order-cost-calculations/source.xlsx',
        'advance_paid' => true,
        'advance_amount' => 500,
        'subtotal' => 1000,
        'tax_amount' => 150,
        'total' => 1150,
        'status' => 'completed',
        'production_started_at' => now()->subDays(9),
        'materials_fully_issued_at' => now()->subDays(8),
        'notified_late_at' => now()->subDay(),
    ]);

    tap($jobOrder, fn (JobOrder $record) => $record->updateQuietly([

        'designer_id' => User::factory()->create()->id,
        'typist_id' => User::factory()->create()->id,
        'name' => 'Carton',
        'quantity' => 100,
        'subtotal' => 1000,
        'paper' => [['inventory_item_id' => 5, 'required_quantity' => 20]],
        'deliverables' => [['label' => 'Dieline', 'type' => 'artwork']],
        'status' => 'completed',
        'size' => 'A4',
        'instructions' => 'Repeat exactly',
    ]));

    $jobOrder->setAttribute('job_orders_count', 1);
    $jobOrder->setAttribute('completed_job_orders_count', 1);

    $duplicate = app(DuplicateJobOrder::class)->handle($jobOrder);

    expect($duplicate->id)->not->toBe($jobOrder->id)
        ->and($duplicate->job_order_number)->not->toBe($jobOrder->job_order_number)
        ->and((string) $duplicate->status)->toBe('draft')
        ->and($duplicate->submission_date->isToday())->toBeTrue()
        ->and($duplicate->due_date->isToday())->toBeTrue()
        ->and((float) $duplicate->advance_amount)->toBe(0.0)
        ->and($duplicate->advance_paid)->toBeFalse()
        ->and($duplicate->production_started_at)->toBeNull()
        ->and($duplicate->materials_fully_issued_at)->toBeNull()
        ->and($duplicate->notified_late_at)->toBeNull()
        ->and($duplicate->cost_calc_file)->toBe($jobOrder->cost_calc_file)
        ->and(collect([$duplicate]))->toHaveCount(1);

    $duplicateTask = collect([$duplicate])->first();

    expect($duplicateTask->id)->toBe($duplicate->id)
        ->and($duplicateTask->name)->toBe('Carton')
        ->and((float) $duplicateTask->quantity)->toBe(100.0)
        ->and((float) $duplicateTask->subtotal)->toBe(1000.0)
        ->and($duplicateTask->paper)->toBe([['inventory_item_id' => 5, 'required_quantity' => 20]])
        ->and($duplicateTask->deliverables)->toBe([['label' => 'Dieline', 'type' => 'artwork']])
        ->and($duplicateTask->designer_id)->toBeNull()
        ->and($duplicateTask->typist_id)->toBeNull()
        ->and((string) $duplicateTask->status)->toBe('draft')
        ->and($duplicateTask->instructions)->toBe('Repeat exactly');
});

it('registers reorder actions where job orders are managed', function (): void {
    expect(file_get_contents(app_path('Filament/Resources/JobOrders/Actions/JobOrderActions.php')))
        ->toContain('public static function make(bool $includeDelete = true): ActionGroup')
        ->toContain('ActionGroup::make(array_filter([')
        ->toContain("Action::make('reorder')")
        ->toContain('DuplicateJobOrder::class')
        ->toContain("in_array((string) \$record->status, ['completed', 'cancelled'], true)")
        ->toContain("JobOrderResource::getUrl('edit', ['record' => \$duplicate])")
        ->and(file_get_contents(app_path('Filament/Resources/JobOrders/Tables/JobOrdersTable.php')))
        ->toContain('JobOrderActions::make()')
        ->and(file_get_contents(app_path('Filament/Resources/JobOrders/Pages/EditJobOrder.php')))
        ->toContain('JobOrderActions::make(includeDelete: true)')
        ->not->toContain("Action::make('reorder')")
        ->and(file_get_contents(app_path('Filament/Resources/JobOrders/Pages/ViewJobOrder.php')))
        ->toContain('JobOrderActions::make()')
        ->not->toContain("Action::make('reorder')");
});

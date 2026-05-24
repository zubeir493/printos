<?php

use App\Models\Artwork;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\StockMovement;
use App\Models\TextFile;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\TypistAssignedToTask;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('does not start production for books until required deliverables are ready', function () {
    $jobOrder = JobOrder::factory()->create([
        'job_type' => 'books',
    ]);

    $task = JobOrderTask::factory()->create([
        'job_order_id' => $jobOrder->id,
        'quantity' => 1,
        'status' => 'design',
        'deliverables' => [
            [
                'type' => 'artwork',
                'label' => 'Cover Artwork',
                'required' => true,
                'min_count' => 1,
            ],
            [
                'type' => 'text_file',
                'label' => 'Text File',
                'required' => true,
                'min_count' => 1,
            ],
        ],
    ]);

    Artwork::create([
        'job_order_task_id' => $task->id,
        'filename' => 'cover.pdf',
        'deliverable' => 'Cover Artwork',
        'is_approved' => true,
    ]);

    expect($task->canStartProduction())->toBeFalse();

    $task->updateStatus();

    expect($task->fresh()->status)->toBe('draft');
});

it('completes a book task only when deliverables are ready and quantity is produced', function () {
    $jobOrder = JobOrder::factory()->create([
        'job_type' => 'books',
    ]);

    $task = JobOrderTask::factory()->create([
        'job_order_id' => $jobOrder->id,
        'quantity' => 1,
        'status' => 'design',
        'deliverables' => [
            [
                'type' => 'artwork',
                'label' => 'Cover Artwork',
                'required' => true,
                'min_count' => 1,
            ],
            [
                'type' => 'text_file',
                'label' => 'Text File',
                'required' => true,
                'min_count' => 1,
            ],
        ],
    ]);

    Artwork::create([
        'job_order_task_id' => $task->id,
        'filename' => 'cover.pdf',
        'deliverable' => 'Cover Artwork',
        'is_approved' => true,
    ]);

    TextFile::create([
        'job_order_task_id' => $task->id,
        'filename' => 'text.pdf',
        'original_name' => 'text.pdf',
        'deliverable' => 'Text File',
        'is_approved' => true,
    ]);

    StockMovement::create([
        'inventory_item_id' => InventoryItem::factory()->create()->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'type' => 'production_output',
        'reference_type' => JobOrderTask::class,
        'reference_id' => $task->id,
        'quantity' => 1,
        'unit_cost' => 0,
        'total_cost' => 0,
        'movement_date' => now()->toDateString(),
    ]);

    expect($task->canStartProduction())->toBeTrue();

    $task->updateStatus();

    expect($task->fresh()->status)->toBe('completed');
});

it('keeps the existing production behavior for non-book tasks without deliverables', function () {
    $jobOrder = JobOrder::factory()->create([
        'job_type' => 'packages',
    ]);

    $task = JobOrderTask::factory()->create([
        'job_order_id' => $jobOrder->id,
        'quantity' => 1,
        'status' => 'design',
    ]);

    Artwork::create([
        'job_order_task_id' => $task->id,
        'filename' => 'package.pdf',
        'is_approved' => true,
    ]);

    $task->updateStatus();

    expect($task->fresh()->status)->toBe('production');
});

it('keeps a task in draft when only a typist is assigned', function () {
    $jobOrder = JobOrder::factory()->create([
        'job_type' => 'books',
    ]);

    $task = JobOrderTask::factory()->create([
        'job_order_id' => $jobOrder->id,
        'quantity' => 1,
        'status' => 'draft',
    ]);

    $typist = User::factory()->create([
        'role' => UserRole::Typist,
    ]);

    $task->update(['typist_id' => $typist->id]);
    $task->updateStatus();

    expect($task->fresh()->status)->toBe('draft');
});

it('creates a database notification when a typist is assigned', function () {
    $jobOrder = JobOrder::factory()->create([
        'job_type' => 'books',
    ]);

    $task = JobOrderTask::factory()->create([
        'job_order_id' => $jobOrder->id,
        'quantity' => 1,
        'status' => 'draft',
    ]);

    $typist = User::factory()->create([
        'role' => UserRole::Typist,
    ]);

    $task->update(['typist_id' => $typist->id]);

    expect($task->fresh()->typist_id)->toBe($typist->id);
    expect(
        DB::table('notifications')
            ->where('notifiable_id', $typist->id)
            ->where('type', TypistAssignedToTask::class)
            ->exists()
    )->toBeTrue();
});

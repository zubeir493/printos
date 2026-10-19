<?php

use App\Models\Artwork;
use App\Models\Dispatch;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Proforma;
use App\Models\ProformaTask;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\JobOrderPolicy;
use App\Services\Accounting\CreateSalesJournalEntry;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\Services\JobOrders\JobOrderWorkflow;
use App\Services\JobOrders\LogJobOrderProduction;
use App\Services\Proformas\ProformaWorkflowService;
use App\Services\SaveDispatch;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates one independently billed job per quotation line and reconciles VAT', function () {
    $proforma = Proforma::factory()->create(['status' => 'approved', 'subtotal' => 0.03, 'tax_amount' => 0.01, 'total' => 0.04]);
    ProformaTask::factory()->for($proforma)->count(3)->create(['task_cost' => 0.01, 'unit_price' => 0.01, 'quantity' => 1]);
    $jobs = app(ProformaWorkflowService::class)->createJobOrders($proforma);
    expect($jobs)->toHaveCount(3)->and($jobs->sum('subtotal'))->toBe(0.03)
        ->and($jobs->sum('tax_amount'))->toBe(0.01)->and($jobs->sum('total'))->toBe(0.04)
        ->and($jobs->pluck('job_order_number')->unique())->toHaveCount(3)
        ->and(app(ProformaWorkflowService::class)->createJobOrders($proforma)->modelKeys())->toBe($jobs->modelKeys());
    expect(Schema::hasTable('job_order_tasks'))->toBeFalse();
    foreach (['artworks', 'text_files', 'dielines', 'material_requests', 'production_plan_items', 'dispatch_items'] as $table) {
        expect(Schema::hasColumn($table, 'job_order_id'))->toBeTrue()
            ->and(Schema::hasColumn($table, 'job_order_task_id'))->toBeFalse();
    }
});

it('rolls back an invalid multi-line conversion', function () {
    $proforma = Proforma::factory()->create(['status' => 'approved']);
    ProformaTask::factory()->for($proforma)->create(['task_cost' => 500]);
    expect(fn () => app(ProformaWorkflowService::class)->createJobOrders($proforma))->toThrow(ValidationException::class);
    expect(JobOrder::count())->toBe(0)->and($proforma->fresh()->status)->toBe('approved');
});

it('starts typist work and requires all deliverables before production', function () {
    $typist = User::factory()->create(['role' => UserRole::Typist]);
    $job = JobOrder::factory()->create(['deliverables' => [['type' => 'artwork', 'label' => 'Cover']]]);
    $job->update(['typist_id' => $typist->id]);
    expect((string) $job->fresh()->status)->toBe('design');
    expect(fn () => app(JobOrderWorkflow::class)->transition($job, 'production'))->toThrow(ValidationException::class);
    Artwork::create(['job_order_id' => $job->id, 'filename' => 'cover.pdf', 'deliverable' => 'Cover', 'is_approved' => true]);
    expect((string) $job->fresh()->status)->toBe('production');
});

it('completes client jobs in either production and payment order with one sale journal', function (bool $paidFirst) {
    $job = JobOrder::factory()->create(['status' => 'production', 'production_mode' => 'make_to_order', 'quantity' => 10]);
    Notification::fake();
    $pay = fn () => Payment::factory()->create([
        'payable_type' => JobOrder::class, 'payable_id' => $job->id, 'partner_id' => $job->partner_id,
        'amount' => 100, 'withholding_amount' => 0, 'direction' => 'inbound', 'method' => 'cash', 'transaction_type' => 'customer_receipt',
    ]);
    if ($paidFirst) {
        $pay();
    }
    app(LogJobOrderProduction::class)->handle($job, Warehouse::factory()->create()->id, 10);
    if (! $paidFirst) {
        expect((string) $job->fresh()->status)->toBe('production')->and($job->fresh()->production_completed_at)->not->toBeNull();
        $pay();
    }
    $job->syncCompletionStatus();
    expect((string) $job->fresh()->status)->toBe('completed')
        ->and(JournalEntry::where('source_type', JobOrder::class)->where('source_id', $job->id)->count())->toBe(1);
    $journal = JournalEntry::where('source_type', JobOrder::class)->firstOrFail();
    expect($journal->journalItems->sum('debit'))->toBe(100.0)->and($journal->journalItems->sum('credit'))->toBe(100.0);
})->with([true, false]);

it('completes an internal job without payments or a sales journal and uses selected output identity', function () {
    $job = JobOrder::factory()->create(['status' => 'production', 'production_mode' => 'make_to_stock', 'partner_id' => null, 'quantity' => 2]);
    $item = InventoryItem::factory()->create(['type' => 'finished_good', 'name' => 'Reusable Product']);
    app(LogJobOrderProduction::class)->handle($job, Warehouse::factory()->create()->id, 2, $item->id);
    app(CreateSalesJournalEntry::class)->handle($job->fresh());
    expect((string) $job->fresh()->status)->toBe('completed')->and($job->fresh()->output_inventory_item_id)->toBe($item->id)
        ->and(JournalEntry::where('source_type', JobOrder::class)->count())->toBe(0);
});

it('reverses dispatch stock correctly when quantities and warehouses change and on deletion', function () {
    $job = JobOrder::factory()->create(['status' => 'production', 'production_mode' => 'make_to_order', 'quantity' => 10]);
    $first = Warehouse::factory()->create();
    $second = Warehouse::factory()->create();
    app(LogJobOrderProduction::class)->handle($job, $first->id, 5);
    app(LogJobOrderProduction::class)->handle($job, $second->id, 5);
    $dispatch = Dispatch::factory()->create(['job_order_id' => $job->id, 'warehouse_id' => $first->id]);
    app(SaveDispatch::class)->handle($dispatch, 3);
    $dispatch->update(['warehouse_id' => $second->id]);
    app(SaveDispatch::class)->handle($dispatch, 4);
    expect((float) InventoryBalance::where('warehouse_id', $first->id)->where('inventory_item_id', $job->fresh()->output_inventory_item_id)->value('quantity_on_hand'))->toBe(5.0)
        ->and((float) InventoryBalance::where('warehouse_id', $second->id)->where('inventory_item_id', $job->fresh()->output_inventory_item_id)->value('quantity_on_hand'))->toBe(1.0);
    $dispatch->delete();
    expect((float) InventoryBalance::where('warehouse_id', $second->id)->where('inventory_item_id', $job->fresh()->output_inventory_item_id)->value('quantity_on_hand'))->toBe(5.0);
});

it('blocks negative output, manual premature completion and changing an output product', function () {
    $job = JobOrder::factory()->create(['status' => 'production', 'production_mode' => 'make_to_stock', 'quantity' => 10]);
    $warehouse = Warehouse::factory()->create();
    expect(fn () => app(LogJobOrderProduction::class)->handle($job, $warehouse->id, -1))->toThrow(ValidationException::class);
    expect(fn () => app(JobOrderWorkflow::class)->transition($job, 'completed'))->toThrow(ValidationException::class);
    app(LogJobOrderProduction::class)->handle($job, $warehouse->id, 1);
    $differentItem = InventoryItem::factory()->create(['type' => 'finished_good']);
    expect(fn () => app(LogJobOrderProduction::class)->handle($job, $warehouse->id, 1, $differentItem->id))->toThrow(ValidationException::class);
});

it('reopens a completed job when a required approval is revoked', function () {
    $job = JobOrder::factory()->create(['status' => 'design', 'quantity' => 2, 'production_mode' => 'make_to_stock', 'deliverables' => [['type' => 'artwork', 'label' => 'Cover']]]);
    $artwork = Artwork::create(['job_order_id' => $job->id, 'filename' => 'cover.pdf', 'deliverable' => 'Cover', 'is_approved' => true]);
    $warehouse = Warehouse::factory()->create();
    app(LogJobOrderProduction::class)->handle($job, $warehouse->id, 2);
    expect((string) $job->fresh()->status)->toBe('completed');
    $artwork->update(['is_approved' => false]);
    expect((string) $job->fresh()->status)->toBe('production')->and($job->fresh()->completed_at)->toBeNull();
    expect(fn () => app(LogJobOrderProduction::class)->handle($job, $warehouse->id, 1))->toThrow(ValidationException::class);
    $artwork->update(['is_approved' => true]);
    expect((string) $job->fresh()->status)->toBe('completed');
});

it('rolls back dispatch edits that exceed warehouse stock', function () {
    $job = JobOrder::factory()->create(['status' => 'production', 'quantity' => 10]);
    $warehouse = Warehouse::factory()->create();
    app(LogJobOrderProduction::class)->handle($job, $warehouse->id, 5);
    $dispatch = Dispatch::factory()->create(['job_order_id' => $job->id, 'warehouse_id' => $warehouse->id]);
    app(SaveDispatch::class)->handle($dispatch, 3);
    expect(fn () => app(SaveDispatch::class)->handle($dispatch, 6))->toThrow(ValidationException::class);
    expect((float) $dispatch->dispatchItems()->sum('quantity'))->toBe(3.0)
        ->and((float) InventoryBalance::where('warehouse_id', $warehouse->id)->where('inventory_item_id', $job->fresh()->output_inventory_item_id)->value('quantity_on_hand'))->toBe(2.0);
});

it('preserves accepted source prices when VAT settings change', function () {
    $proforma = Proforma::factory()->create(['status' => 'approved', 'subtotal' => 100, 'tax_amount' => 15, 'total' => 115]);
    ProformaTask::factory()->for($proforma)->create(['task_cost' => 100, 'quantity' => 1]);
    $job = app(ProformaWorkflowService::class)->createJobOrders($proforma)->first();
    Setting::getSettings()->update(['vat_enabled' => false]);
    $job->recalculateTotals();
    expect((float) $job->fresh()->total)->toBe(115.0);
    expect(fn () => $job->update(['subtotal' => 200]))->toThrow(ValidationException::class);
});

it('limits assigned staff to their jobs and reserves stock and billing actions', function () {
    $designer = User::factory()->create(['role' => UserRole::Design]);
    $typist = User::factory()->create(['role' => UserRole::Typist]);
    $job = JobOrder::factory()->create(['designer_id' => $designer->id, 'typist_id' => $typist->id]);
    $policy = new JobOrderPolicy;
    expect($policy->view($designer, $job))->toBeTrue()->and($policy->view($typist, $job))->toBeTrue()
        ->and($policy->view(User::factory()->create(['role' => UserRole::Design]), $job))->toBeFalse()
        ->and($policy->recordPayment($designer, $job))->toBeFalse()->and($policy->logProduction($typist, $job))->toBeFalse();
});

it('reopens a fully paid job after payment voiding and does not duplicate its sale on repayment', function () {
    $job = JobOrder::factory()->create(['status' => 'production', 'quantity' => 1]);
    app(LogJobOrderProduction::class)->handle($job, Warehouse::factory()->create()->id, 1);
    $attributes = ['payable_type' => JobOrder::class, 'payable_id' => $job->id, 'partner_id' => $job->partner_id,
        'amount' => 100, 'withholding_amount' => 0, 'direction' => 'inbound', 'method' => 'cash', 'transaction_type' => 'customer_receipt'];
    $payment = Payment::factory()->create($attributes);
    expect((string) $job->fresh()->status)->toBe('completed');
    app(VoidPaymentJournalEntry::class)->handle($payment, 'Correction');
    expect((string) $job->fresh()->status)->toBe('production')->and($job->fresh()->balance)->toBe(100.0);
    Payment::factory()->create($attributes);
    expect((string) $job->fresh()->status)->toBe('completed')
        ->and(JournalEntry::where('source_type', JobOrder::class)->where('source_id', $job->id)->count())->toBe(1);
});

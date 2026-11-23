<?php

use App\Filament\Resources\JobOrders\Pages\CreateJobOrder;
use App\Filament\Resources\JobOrders\Pages\ViewJobOrder;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Models\Account;
use App\Models\Artwork;
use App\Models\CostEstimate;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\JobOrders\DuplicateJobOrder;
use App\Services\JobOrders\JobOrderWorkflow;
use App\Services\JobOrders\JobProductionCosting;
use App\Services\JobOrders\LogJobOrderProduction;
use App\Services\VoidSalesOrder;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Setting::getSettings()->update(['vat_enabled' => true, 'vat_rate' => 15]);
});

it('takes an internal book from a short draft to reviewed stock without client billing', function (): void {
    $warehouse = Warehouse::factory()->create();
    $paper = InventoryItem::factory()->create(['type' => 'raw_material', 'average_cost' => 2]);
    InventoryBalance::create(['inventory_item_id' => $paper->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 20]);
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Operations]));

    Livewire::test(CreateJobOrder::class)->fillForm([
        'name' => 'Our annual print edition', 'quantity' => 10, 'job_type' => 'books',
        'production_mode' => 'make_to_stock',
        'production_due_date' => today()->addWeek()->toDateString(),
        'unit_price' => 999,
    ])->assertFormFieldDoesNotExist('production_warehouse_id')->call('create')->assertHasNoFormErrors();
    $job = JobOrder::query()->sole();
    expect($job->isInternal())->toBeTrue()
        ->and($job->partner_id)->toBeNull()->and($job->cost_calc_file)->toBeNull()
        ->and($job->production_warehouse_id)->toBeNull()
        ->and((float) $job->total)->toBe(0.0);
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])->assertSee('Internal job')
        ->assertSee('Enter the page count and binding type')->assertDontSee('Client billing');

    $workflow = app(JobOrderWorkflow::class);
    $workflow->transition($job, 'design');
    expect(fn () => $workflow->transition($job, 'production'))->toThrow(ValidationException::class);
    $job->update([
        'size' => 'A5', 'services' => ['edition' => 'Second edition', 'page_no' => 96, 'binding_type' => 'perfect'],
        'paper' => [['inventory_item_id' => $paper->id, 'required_quantity' => 8, 'reserve_quantity' => 2]],
    ]);
    $workflow->transition($job, 'production');
    $log = app(LogJobOrderProduction::class);
    expect(fn () => $log->handle($job, null, 10))->toThrow(ValidationException::class, 'Issue the planned quantities');
    $inventory = app(InventoryService::class);
    $issue = $inventory->consumeStock($paper->id, $warehouse->id, 9, JobOrder::class, $job->id);
    $inventory->returnJobMaterials($paper->id, $warehouse->id, 1, 2, $job->id);
    $materialsJournal = JournalEntry::query()->where('source_type', StockMovement::class)->where('source_id', $issue->id)->sole();
    expect((float) $materialsJournal->total_debit)->toBe(18.0)
        ->and((float) InventoryBalance::query()->where('inventory_item_id', $paper->id)->value('quantity_on_hand'))->toBe(12.0);
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])->callAction('cancel_job_order', ['cancel_reason' => 'Inventory check'])
        ->assertNotified('Job cannot be cancelled');
    expect($job->fresh()->remarks)->toBeNull();
    $operator = auth()->user();
    $this->actingAs(User::factory()->create(['role' => UserRole::Production]));
    Filament::setCurrentPanel(Filament::getPanel('production'));
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])->assertDontSee('Production costing')
        ->assertActionHidden('receive_finished_stock')->assertActionVisible('log_production');
    $this->actingAs($operator);
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])->callAction('log_production', ['quantity' => 4])
        ->assertNotified('Production logged');
    $log->handle($job, null, 6);
    expect($job->produced_quantity)->toBe(10.0)->and($job->productionMovements()->count())->toBe(0)
        ->and($job->canComplete())->toBeFalse();

    $costing = app(JobProductionCosting::class);
    $costing->saveWorksheet($job, ['labor' => 10, 'press' => 8, 'finishing' => 4, 'overhead' => 2], actual: true);
    expect(fn () => $costing->receiveFinishedStock($job, $warehouse->id, null, 'exempt'))->toThrow(AuthorizationException::class);
    $finance = User::factory()->create(['role' => UserRole::Finance]);
    $this->actingAs($finance);
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])
        ->callAction('receive_finished_stock', ['warehouse_id' => null, 'sales_tax_code' => 'exempt'])
        ->assertHasFormErrors(['warehouse_id' => 'required']);
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])->assertSee('Production costing')
        ->callAction('receive_finished_stock', ['warehouse_id' => $warehouse->id, 'sales_tax_code' => 'exempt'])
        ->assertNotified('Finished stock received');
    $costing->receiveFinishedStock($job->refresh(), $warehouse->id, null, 'exempt');
    $movement = $job->productionMovements()->sole();
    $journal = JournalEntry::query()->where('source_type', JobOrder::class)->where('source_id', $job->id)->sole();

    expect((string) $job->status)->toBe('completed')->and($job->cost_reviewed_by)->toBe($finance->id)
        ->and($job->production_warehouse_id)->toBe($warehouse->id)
        ->and((float) $movement->total_cost)->toBe(40.0)->and((float) $movement->unit_cost)->toBe(4.0)
        ->and((float) $job->outputInventoryItem->average_cost)->toBe(4.0)->and($job->outputInventoryItem->stockOnHand())->toBe(10.0)
        ->and($job->outputInventoryItem->sales_tax_code)->toBe('exempt')
        ->and((float) $journal->journalItems()->sum('debit'))->toBe((float) $journal->journalItems()->sum('credit'))
        ->and($job->invoices()->count())->toBe(0)->and($job->payments()->count())->toBe(0);
    expect(fn () => $movement->delete())->toThrow(ValidationException::class);
    $wip = Account::query()->where('code', '1510')->sole()->journalItems();
    expect((float) (clone $wip)->sum('debit'))->toBe((float) $wip->sum('credit'));

    $reorder = app(DuplicateJobOrder::class)->handle($job);
    expect($reorder->isInternal())->toBeTrue()->and($reorder->output_inventory_item_id)->toBe($job->output_inventory_item_id)
        ->and($reorder->produced_quantity)->toBe(0.0)->and($reorder->actual_production_costs)->toBeNull();
});

it('requires approved costs and proofs for a manual client production release', function (): void {
    $customer = Partner::factory()->create(['is_customer' => true]);
    $job = JobOrder::factory()->create([
        'uses_production_ticket' => true, 'production_mode' => 'make_to_order', 'partner_id' => $customer->id,
        'status' => 'design', 'job_type' => 'packages', 'quantity' => 5, 'size' => 'A4', 'subtotal' => 500, 'tax_amount' => 75,
        'production_due_date' => today()->addWeek(), 'deliverables' => [['label' => 'Proof', 'type' => 'artwork']],
    ]);
    $estimate = CostEstimate::factory()->create([
        'status' => 'finalized', 'job_type' => 'packages', 'quantity' => 5, 'partner_id' => $customer->id, 'overhead_amount' => 20,
    ]);
    $estimate->lines()->create(['category' => 'Machine', 'label' => 'Press', 'quantity' => 1, 'unit_cost' => 30, 'total' => 30, 'unit' => 'hour']);
    $this->actingAs(User::factory()->create(['role' => UserRole::Operations]));
    $costing = app(JobProductionCosting::class);
    $workflow = app(JobOrderWorkflow::class);
    $costing->useEstimate($job, $estimate->id);
    Artwork::create(['job_order_id' => $job->id, 'deliverable' => 'Proof', 'filename' => 'proof.pdf', 'is_approved' => true]);
    expect(fn () => $workflow->transition($job, 'production'))->toThrow(ValidationException::class, 'approve the estimated');
    expect(fn () => $costing->approveEstimate($job))->toThrow(AuthorizationException::class);
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $costing->approveEstimate($job);
    expect($job->estimated_production_costs['press'])->toEqual(30)
        ->and((string) $job->status)->toBe('design')->and($job->productionReleaseIssues())->toBe([]);
    $this->actingAs(User::factory()->create(['role' => UserRole::Operations]));
    $workflow->transition($job, 'production');
    expect((string) $job->status)->toBe('production')->and((float) $job->tax_amount)->toBe(75.0);
});

it('uses the sold product tax code and posts and reverses the actual cost of sold copies', function (): void {
    $warehouse = Warehouse::factory()->create();
    $customer = Partner::factory()->create(['is_customer' => true]);
    $book = InventoryItem::factory()->create([
        'type' => 'finished_good', 'is_sellable' => true, 'unit' => 'pcs', 'sales_tax_code' => 'exempt',
        'average_cost' => 4, 'price' => 15,
    ]);
    $book->forceFill(['production_valued' => true])->save();
    $standard = InventoryItem::factory()->create([
        'type' => 'finished_good', 'is_sellable' => true, 'unit' => 'pcs', 'average_cost' => 0, 'price' => 20,
    ]);
    foreach ([$book, $standard] as $item) {
        InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 10]);
    }
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(CreateSalesOrder::class)->fillForm([
        'warehouse_id' => $warehouse->id, 'partner_id' => $customer->id,
        'payment_mode' => 'cash', 'payment_method' => 'cash', 'order_date' => today()->toDateString(),
        'salesOrderItems' => [
            ['inventory_item_id' => $book->id, 'quantity' => 2, 'unit_price' => 15, 'total' => 30],
            ['inventory_item_id' => $standard->id, 'quantity' => 1, 'unit_price' => 20, 'total' => 20],
        ],
    ])->call('create')->assertHasNoFormErrors();
    $sale = SalesOrder::query()->sole();
    $journal = JournalEntry::query()->where('source_type', SalesOrder::class)->where('source_id', $sale->id)->sole();
    $cogs = Account::query()->where('code', '5000')->sole();
    expect($sale->status)->toBe(SalesOrder::STATUS_COMPLETED)->and((float) $sale->tax_amount)->toBe(3.0)
        ->and((float) $sale->total)->toBe(53.0)->and((float) $journal->journalItems()->where('account_id', $cogs->id)->sole()->debit)->toBe(8.0);
    $book->update(['sales_tax_code' => 'standard']);
    expect($sale->salesOrderItems()->where('inventory_item_id', $book->id)->sole()->sales_tax_code)->toBe('exempt');
    app(VoidSalesOrder::class)->handle($sale);
    $reversal = JournalEntry::query()->where('reversal_of_journal_entry_id', $journal->id)->sole();
    expect((float) $reversal->journalItems()->where('account_id', $cogs->id)->sole()->credit)->toBe(8.0)
        ->and($book->stockOnHand())->toBe(10.0)
        ->and((float) StockMovement::query()->where('type', 'sale_return')->where('inventory_item_id', $book->id)->sole()->total_cost)->toBe(8.0);
});

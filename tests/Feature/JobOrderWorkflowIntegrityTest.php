<?php

use App\Filament\Operations\Widgets\OperationsHealthStats;
use App\Filament\Resources\Artworks\Pages\ListArtworks;
use App\Filament\Resources\JobOrders\Pages\ListJobOrders;
use App\Filament\Resources\JobOrders\Pages\ViewJobOrder;
use App\Filament\Resources\JobOrders\RelationManagers\ArtworksRelationManager;
use App\Filament\Resources\JobOrders\RelationManagers\MaterialRequestsRelationManager;
use App\Filament\Resources\JobOrders\RelationManagers\TextFilesRelationManager;
use App\Filament\Support\MaterialRequestActionForms;
use App\Models\Artwork;
use App\Models\Dispatch;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\MaterialRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\JobOrders\LogJobOrderProduction;
use App\Services\MaterialIssueService;
use App\Services\ReturnJobMaterials;
use App\Services\SaveDispatch;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);
beforeEach(fn () => Notification::fake());

it('cancelling dispatch restores stock and releases produced quantity', function (): void {
    $job = JobOrder::factory()->create(['status' => 'production', 'quantity' => 10]);
    $warehouse = Warehouse::factory()->create();
    app(LogJobOrderProduction::class)->handle($job, $warehouse->id, 10);
    $dispatch = Dispatch::factory()->create(['job_order_id' => $job->id, 'warehouse_id' => $warehouse->id]);
    app(SaveDispatch::class)->handle($dispatch, 10);
    $dispatch->update(['status' => 'cancelled']);
    expect($job->fresh()->remaining_quantity)->toBe(10.0)
        ->and((float) InventoryBalance::where('inventory_item_id', $job->fresh()->output_inventory_item_id)->value('quantity_on_hand'))->toBe(10.0);
});

it('requires approval for cumulative material consumption across requests', function (): void {
    $item = InventoryItem::factory()->create(['type' => 'raw_material']);
    $warehouse = Warehouse::factory()->create();
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 500]);
    $job = JobOrder::factory()->create(['status' => 'production', 'paper' => [['inventory_item_id' => $item->id, 'required_quantity' => 100]]]);
    $data = ['reason' => 'Run', 'items' => [['inventory_item_id' => $item->id, 'paper_index' => 0, 'requested_quantity' => 100]]];
    MaterialRequestActionForms::createMaterialRequests($job, $data);
    MaterialRequestActionForms::createMaterialRequests($job, $data);
    $requests = $job->materialRequests()->orderBy('id')->get();
    expect(app(MaterialIssueService::class)->issue($requests[0], $warehouse->id, 100)['status'])->toBe('issued');
    expect(app(MaterialIssueService::class)->issue($requests[1], $warehouse->id, 100)['status'])->toBe('pending_approval');
    expect($job->issuedQuantityFor($item->id))->toBe(100.0);
});

it('blocks material issue on a cancelled job', function (): void {
    $job = JobOrder::factory()->create(['status' => 'cancelled']);
    $item = InventoryItem::factory()->create(['type' => 'raw_material']);
    $warehouse = Warehouse::factory()->create();
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 100]);
    $request = MaterialRequest::create(['job_order_id' => $job->id, 'inventory_item_id' => $item->id, 'requested_quantity' => 100, 'required_quantity' => 100]);
    expect(fn () => app(MaterialIssueService::class)->issue($request, $warehouse->id, 100))->toThrow(ValidationException::class);
});

it('revokes approval when replacing a file', function (): void {
    $job = JobOrder::factory()->create(['status' => 'design', 'deliverables' => [['type' => 'artwork', 'label' => 'Cover']]]);
    $artwork = Artwork::create(['job_order_id' => $job->id, 'filename' => 'approved.pdf', 'deliverable' => 'Cover', 'is_approved' => true]);
    $artwork->update(['filename' => 'unreviewed.pdf']);
    expect($artwork->fresh()->is_approved)->toBeFalse()->and($job->fresh()->isReadyForProduction())->toBeFalse();
});

it('reopens production after output correction and clears completion timestamps', function (): void {
    $job = JobOrder::factory()->create(['status' => 'production', 'production_mode' => 'make_to_stock', 'quantity' => 1]);
    $movement = app(LogJobOrderProduction::class)->handle($job, Warehouse::factory()->create()->id, 1);
    $movement->delete();
    expect((string) $job->fresh()->status)->toBe('production')->and($job->fresh()->canComplete())->toBeFalse()
        ->and($job->fresh()->production_completed_at)->toBeNull()->and($job->fresh()->completed_at)->toBeNull();
    $job->syncCompletionStatus();
    expect((string) $job->fresh()->status)->toBe('production')->and($job->fresh()->production_completed_at)->toBeNull();
});

it('allows operations to approve existing text files on active job views', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Operations]));
    $job = JobOrder::factory()->create(['status' => 'design']);
    $text = $job->textFiles()->create(['filename' => 'text.pdf', 'original_name' => 'text.pdf', 'is_approved' => false]);
    Livewire::test(TextFilesRelationManager::class, ['ownerRecord' => $job, 'pageClass' => ViewJobOrder::class])
        ->assertActionVisible(TestAction::make('edit')->table($text))
        ->callAction(TestAction::make('approve')->table($text));
    expect($text->fresh()->is_approved)->toBeTrue();
});

it('prevents production staff from issuing materials through relation managers', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('production'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Production]));
    $job = JobOrder::factory()->create(['status' => 'production']);
    expect(Gate::allows('manageMaterials', $job))->toBeFalse();
    $item = InventoryItem::factory()->create(['type' => 'raw_material']);
    $warehouse = Warehouse::factory()->create();
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 100]);
    $request = MaterialRequest::create(['job_order_id' => $job->id, 'inventory_item_id' => $item->id, 'requested_quantity' => 100, 'required_quantity' => 100]);
    Livewire::test(MaterialRequestsRelationManager::class, ['ownerRecord' => $job, 'pageClass' => ViewJobOrder::class])
        ->assertActionHidden(TestAction::make('issue')->table($request));
    expect(fn () => app(MaterialIssueService::class)->issue($request, $warehouse->id, 100, auth()->user()))->toThrow(AuthorizationException::class);
    expect((float) $request->fresh()->issued_quantity)->toBe(0.0);
});

it('stores same named artwork uploads separately for different jobs', function (): void {
    Storage::fake('s3');
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Operations]));
    foreach (JobOrder::factory()->count(2)->create(['status' => 'design']) as $job) {
        Livewire::test(ListArtworks::class)
            ->callAction('create', [
                'job_order_id' => $job->id,
                'filename' => UploadedFile::fake()->create('cover.pdf', 1, 'application/pdf'),
                'is_approved' => false,
            ])->assertHasNoFormErrors();
    }
    expect(Artwork::count())->toBe(2)->and(Artwork::pluck('filename')->unique())->toHaveCount(2);
});

it('returns materials to their respective source warehouses', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Warehouse]));
    $job = JobOrder::factory()->create(['status' => 'production']);
    $item = InventoryItem::factory()->create(['type' => 'raw_material']);
    $first = Warehouse::factory()->create();
    $second = Warehouse::factory()->create();
    foreach ([$first, $second] as $warehouse) {
        InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 100]);
    }
    $request = MaterialRequest::create(['job_order_id' => $job->id, 'inventory_item_id' => $item->id, 'requested_quantity' => 10, 'required_quantity' => 10]);
    app(MaterialIssueService::class)->issue($request, $first->id, 5);
    $this->travel(1)->seconds();
    app(MaterialIssueService::class)->issue($request, $second->id, 5);
    $rows = app(ReturnJobMaterials::class)->rowsFor($job);
    expect($rows)->toHaveCount(2);
    foreach ($rows as &$row) {
        $row['quantity'] = $row['returnable_quantity'];
    }
    unset($row);
    Livewire::test(ListJobOrders::class)
        ->callAction(TestAction::make('return_materials')->table($job), ['items' => $rows])->assertHasNoFormErrors();
    expect((float) InventoryBalance::where('inventory_item_id', $item->id)->where('warehouse_id', $first->id)->value('quantity_on_hand'))->toBe(100.0)
        ->and((float) InventoryBalance::where('inventory_item_id', $item->id)->where('warehouse_id', $second->id)->value('quantity_on_hand'))->toBe(100.0);
});

it('rejects pending material approvals after a job is cancelled', function (): void {
    $job = JobOrder::factory()->create(['status' => 'production']);
    $item = InventoryItem::factory()->create(['type' => 'raw_material']);
    $warehouse = Warehouse::factory()->create();
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 100]);
    $request = MaterialRequest::create(['job_order_id' => $job->id, 'inventory_item_id' => $item->id, 'required_quantity' => 1, 'requested_quantity' => 10]);
    $result = app(MaterialIssueService::class)->issue($request, $warehouse->id, 10);
    $job->cancel();
    expect(fn () => app(MaterialIssueService::class)->approve($result['approval']))->toThrow(ValidationException::class);
    expect($result['approval']->fresh()->status)->toBe('pending')->and($job->issuedQuantityFor($item->id))->toBe(0.0);
});

it('rejects returns exceeding the source warehouse issue and preserves stock', function (): void {
    $job = JobOrder::factory()->create(['status' => 'production']);
    $item = InventoryItem::factory()->create(['type' => 'raw_material']);
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 100]);
    $request = MaterialRequest::create(['job_order_id' => $job->id, 'inventory_item_id' => $item->id, 'required_quantity' => 10, 'requested_quantity' => 10]);
    app(MaterialIssueService::class)->issue($request, $warehouse->id, 10);
    expect(fn () => app(ReturnJobMaterials::class)->handle($job, [['material_request_id' => $request->id, 'warehouse_id' => $other->id, 'quantity' => 10]]))->toThrow(ValidationException::class);
    expect((float) $request->fresh()->issued_quantity)->toBe(10.0)
        ->and(InventoryBalance::where('warehouse_id', $other->id)->exists())->toBeFalse();
    $job->cancel();
    app(ReturnJobMaterials::class)->handle($job, [['material_request_id' => $request->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]]);
    expect((float) $request->fresh()->issued_quantity)->toBe(0.0);
});

it('clears text approval on replacement and requires operations approval', function (): void {
    $typist = User::factory()->create(['role' => UserRole::Typist]);
    $job = JobOrder::factory()->create(['status' => 'design', 'typist_id' => $typist->id]);
    $file = $job->textFiles()->create(['filename' => 'approved.pdf', 'original_name' => 'approved.pdf', 'is_approved' => true]);
    $this->actingAs($typist);
    $file->update(['filename' => 'new.pdf']);
    expect($file->fresh()->is_approved)->toBeFalse();
    expect(fn () => $file->update(['is_approved' => true]))->toThrow(AuthorizationException::class);
});

it('counts design and production jobs in operations health stats', function (): void {
    JobOrder::factory()->create(['status' => 'design']);
    JobOrder::factory()->create(['status' => 'production']);
    JobOrder::factory()->create(['status' => 'cancelled']);
    $widget = new class extends OperationsHealthStats
    {
        public function activeCount(): mixed
        {
            return $this->getStats()[0]->getValue();
        }
    };
    expect($widget->activeCount())->toBe(2);
});

it('refuses consolidation on a populated database without altering records', function (): void {
    $job = JobOrder::factory()->create();
    $migration = require database_path('migrations/2026_10_13_121614_consolidate_job_order_tasks.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'requires an empty database');
    expect($job->fresh()->name)->toBe($job->name)->and(JobOrder::count())->toBe(1);
});

it('values material returns at their consumption cost rather than the purchase unit price', function (): void {
    $job = JobOrder::factory()->create(['status' => 'production']);
    $item = InventoryItem::factory()->create(['type' => 'raw_material', 'price' => 1000, 'purchase_unit' => 'pack', 'conversion_factor' => 100, 'average_cost' => 10]);
    $warehouse = Warehouse::factory()->create();
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 100]);
    $request = MaterialRequest::create(['job_order_id' => $job->id, 'inventory_item_id' => $item->id, 'required_quantity' => 10, 'requested_quantity' => 10]);
    app(MaterialIssueService::class)->issue($request, $warehouse->id, 10);
    $item->update(['price' => 2000, 'average_cost' => 20]);
    app(ReturnJobMaterials::class)->handle($job, [['material_request_id' => $request->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]]);
    $movement = $job->materialMovements()->where('type', 'material_return')->firstOrFail();
    expect((float) $movement->unit_cost)->toBe(10.0)->and((float) $movement->total_cost)->toBe(100.0);
});

it('eager loads the parent job for relation action authorization', function (): void {
    $job = JobOrder::factory()->create(['status' => 'production']);
    $job->artworks()->create(['filename' => 'artwork.pdf', 'is_approved' => false]);
    $job->textFiles()->create(['filename' => 'text.pdf', 'original_name' => 'text.pdf', 'is_approved' => false]);
    $job->materialRequests()->create(['inventory_item_id' => InventoryItem::factory()->create()->id, 'required_quantity' => 1, 'requested_quantity' => 1]);
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Operations]));
    foreach ([ArtworksRelationManager::class, TextFilesRelationManager::class, MaterialRequestsRelationManager::class] as $manager) {
        $component = Livewire::test($manager, ['ownerRecord' => $job, 'pageClass' => ViewJobOrder::class]);
        expect($component->instance()->getTableRecords()->first()->relationLoaded('jobOrder'))->toBeTrue();
    }
});

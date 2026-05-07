<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\MaterialIssueApproval;
use App\Models\MaterialRequest;
use App\Models\Partner;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialIssueService;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialIssueApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('logging.default', 'errorlog');
    }

    public function test_over_issue_creates_pending_approval_without_consuming_stock(): void
    {
        [$materialRequest, $warehouse, $item, $user] = $this->makeIssueScenario(requiredQuantity: 100, requestedQuantity: 120, issuedQuantity: 0, stock: 500);

        $result = app(MaterialIssueService::class)->issue($materialRequest, $warehouse->id, 120, $user);

        $this->assertSame('pending_approval', $result['status']);

        $this->assertDatabaseHas('material_issue_approvals', [
            'material_request_id' => $materialRequest->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 120.00,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('material_requests', [
            'id' => $materialRequest->id,
            'issued_quantity' => 0.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 500.00,
        ]);
    }

    public function test_approval_consumes_stock_and_updates_issued_quantity(): void
    {
        [$materialRequest, $warehouse, $item, $warehouseUser] = $this->makeIssueScenario(requiredQuantity: 100, requestedQuantity: 120, issuedQuantity: 0, stock: 500);

        app(MaterialIssueService::class)->issue($materialRequest, $warehouse->id, 120, $warehouseUser);

        $approval = MaterialIssueApproval::firstOrFail();
        $approver = User::factory()->create([
            'role' => UserRole::Operations,
        ]);

        app(MaterialIssueService::class)->approve($approval, $approver, 'Approved for urgent run.');

        $this->assertDatabaseHas('material_issue_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'processed_by' => $approver->id,
        ]);

        $this->assertDatabaseHas('material_requests', [
            'id' => $materialRequest->id,
            'issued_quantity' => 120.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 380.00,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'consumption',
            'reference_type' => JobOrder::class,
            'reference_id' => $materialRequest->jobOrderTask->job_order_id,
            'quantity' => -120.00,
        ]);
    }

    public function test_standard_issue_still_consumes_immediately(): void
    {
        [$materialRequest, $warehouse, $item, $user] = $this->makeIssueScenario(requiredQuantity: 100, requestedQuantity: 100, issuedQuantity: 0, stock: 500);

        $result = app(MaterialIssueService::class)->issue($materialRequest, $warehouse->id, 80, $user);

        $this->assertSame('issued', $result['status']);

        $this->assertDatabaseMissing('material_issue_approvals', [
            'material_request_id' => $materialRequest->id,
        ]);

        $this->assertDatabaseHas('material_requests', [
            'id' => $materialRequest->id,
            'issued_quantity' => 80.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 420.00,
        ]);
    }

    protected function makeIssueScenario(int|float $requiredQuantity, int|float $requestedQuantity, int|float $issuedQuantity, int|float $stock): array
    {
        $warehouse = Warehouse::create([
            'name' => 'Raw Materials',
            'code' => 'RAW',
        ]);

        $item = InventoryItem::create([
            'name' => 'Art Card',
            'sku' => 'ART-CARD',
            'unit' => 'sheet',
            'purchase_unit' => 'sheet',
            'conversion_factor' => 1,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 12,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => $stock,
        ]);

        $jobOrder = JobOrder::create([
            'job_order_number' => 'JO-TEST-001',
            'partner_id' => Partner::factory()->create()->id,
            'job_type' => 'books',
            'cost_calc_file' => 'test.pdf',
            'services' => json_encode(['printing']),
            'submission_date' => now()->toDateString(),
            'status' => 'production',
            'remarks' => 'Testing over-issue flow',
            'advance_amount' => 0,
            'advance_paid' => false,
            'total' => 0,
            'production_mode' => 'make_to_order',
        ]);

        $task = JobOrderTask::create([
            'job_order_id' => $jobOrder->id,
            'name' => 'Printing',
            'quantity' => 1000,
            'task_cost' => 0,
            'status' => 'production',
            'paper' => [[
                'inventory_item_id' => $item->id,
                'required_quantity' => $requiredQuantity,
                'reserve_quantity' => max(0, $requestedQuantity - $requiredQuantity),
            ]],
        ]);

        $materialRequest = MaterialRequest::create([
            'job_order_task_id' => $task->id,
            'inventory_item_id' => $item->id,
            'required_quantity' => $requiredQuantity,
            'requested_quantity' => $requestedQuantity,
            'issued_quantity' => $issuedQuantity,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Warehouse,
        ]);

        return [$materialRequest, $warehouse, $item, $user];
    }
}

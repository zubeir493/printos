<?php

namespace Tests\Feature;

use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\Machine;
use App\Models\Partner;
use App\Models\ProductionPlan;
use App\Models\ProductionPlanItem;
use App\Models\ProductionPlanMachine;
use App\Models\ProductionReport;
use App\Models\ProductionReportItem;
use App\Models\ProductionReportMachine;
use App\Models\Proforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_order_can_start_without_approved_artwork()
    {
        $partner = Partner::create([
            'name' => 'Production Client',
            'is_customer' => true,
        ]);

        $jobOrder = JobOrder::create([
            'proforma_id' => Proforma::factory()->create(['partner_id' => $partner->id])->id,
            'partner_id' => $partner->id,
            'job_order_number' => 'JO-PROD-001',
            'job_type' => 'packages',
            'services' => [],
            'status' => 'draft',
            'submission_date' => now(),
            'total' => 100000,
            'advance_amount' => 20000,
        ]);

        $jobOrder->update(['status' => 'active']);

        $this->assertEquals('active', (string) $jobOrder->fresh()->status);
        $this->assertNotNull($jobOrder->fresh()->production_started_at);
    }

    public function test_production_plan_and_report_can_be_created_for_approved_job_order()
    {
        $partner = Partner::create([
            'name' => 'Production Client 2',
            'is_customer' => true,
        ]);

        $jobOrder = JobOrder::create([
            'proforma_id' => Proforma::factory()->create(['partner_id' => $partner->id])->id,
            'partner_id' => $partner->id,
            'job_order_number' => 'JO-PROD-002',
            'job_type' => 'packages',
            'services' => [],
            'status' => 'draft',
            'submission_date' => now(),
            'total' => 80000,
            'advance_amount' => 15000,
        ]);

        $jobOrder->update(['status' => 'active']);

        $this->assertNotNull($jobOrder->fresh()->production_started_at);
        $this->assertEquals('active', $jobOrder->status);

        $machine = Machine::create([
            'name' => 'Offset Press 2',
            'code' => 'OP-2',
        ]);

        $plan = ProductionPlan::create([
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->endOfWeek(),
            'status' => 'approved',
        ]);

        $planMachine = ProductionPlanMachine::create([
            'production_plan_id' => $plan->id,
            'machine_id' => $machine->id,
        ]);

        $task = JobOrderTask::create([
            'job_order_id' => $jobOrder->id,
            'name' => 'Produce Packaging',
            'quantity' => 5000,
            'task_cost' => 50000,
        ]);

        $planItem = ProductionPlanItem::create([
            'production_plan_id' => $plan->id,
            'production_plan_machine_id' => $planMachine->id,
            'machine_id' => $machine->id,
            'job_order_task_id' => $task->id,
            'planned_quantity' => 5000,
            'planned_plates' => 1,
            'planned_rounds' => 2,
        ]);

        $report = ProductionReport::create([
            'production_plan_id' => $plan->id,
            'status' => 'completed',
        ]);

        $reportMachine = ProductionReportMachine::create([
            'production_report_id' => $report->id,
            'production_plan_machine_id' => $planMachine->id,
        ]);

        ProductionReportItem::create([
            'production_report_machine_id' => $reportMachine->id,
            'production_plan_item_id' => $planItem->id,
            'date' => now(),
            'actual_quantity' => 4900,
            'plates_used' => 1,
            'rounds' => 2,
        ]);

        $this->assertDatabaseHas('production_plan_items', [
            'id' => $planItem->id,
            'machine_id' => $machine->id,
        ]);

        $this->assertDatabaseHas('production_report_items', [
            'production_report_machine_id' => $reportMachine->id,
            'production_plan_item_id' => $planItem->id,
            'actual_quantity' => 4900,
        ]);
    }
}

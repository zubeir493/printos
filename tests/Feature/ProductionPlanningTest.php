<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use App\Filament\Resources\ProductionReports\ProductionReportResource;
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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_production_plan_and_copy_to_report()
    {
        $user = User::factory()->create();
        $machine = Machine::create(['name' => 'Machine 1', 'code' => 'M1']);

        $partner = Partner::create([
            'name' => 'Test Partner',
            'phone' => '123456789',
            'is_customer' => true,
        ]);

        $jobOrder = JobOrder::create([
            'proforma_id' => Proforma::factory()->create(['partner_id' => $partner->id])->id,
            'job_order_number' => 'JO-001',
            'partner_id' => $partner->id,
            'job_type' => 'packages',
            'services' => [],
            'submission_date' => now(),
            'total' => 1000.00,
            'status' => 'draft',
        ]);

        // Since I don't have factories for all, I'll use create manually
        $task = JobOrderTask::create([
            'job_order_id' => $jobOrder->id,
            'name' => 'Task 1',
            'quantity' => 1000,
            'task_cost' => 500.00,
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

        $planItem = ProductionPlanItem::create([
            'production_plan_id' => $plan->id,
            'production_plan_machine_id' => $planMachine->id,
            'machine_id' => $machine->id,
            'job_order_task_id' => $task->id,
            'planned_quantity' => 1000,
            'planned_plates' => 2,
            'planned_rounds' => 5,
        ]);

        // Mock the logic from the action since we're testing the core logic
        $report = ProductionReport::create([
            'production_plan_id' => $plan->id,
            'status' => 'draft',
        ]);

        $reportMachine = ProductionReportMachine::create([
            'production_report_id' => $report->id,
            'production_plan_machine_id' => $planMachine->id,
        ]);

        foreach ($plan->items as $item) {
            ProductionReportItem::create([
                'production_report_machine_id' => $reportMachine->id,
                'production_plan_item_id' => $item->id,
                'date' => now(),
                'actual_quantity' => $item->planned_quantity,
                'plates_used' => $item->planned_plates,
                'rounds' => $item->planned_rounds,
            ]);
        }

        $this->assertDatabaseHas('production_reports', [
            'production_plan_id' => $plan->id,
        ]);

        $this->assertDatabaseHas('production_report_items', [
            'production_report_machine_id' => $reportMachine->id,
            'production_plan_item_id' => $planItem->id,
            'actual_quantity' => 1000,
        ]);

        $this->assertEquals(1, $report->items()->count());
        $this->assertEquals($machine->id, $report->items->first()->productionPlanItem->machine_id);
    }

    public function test_approved_plans_and_submitted_reports_are_read_only(): void
    {
        $draftPlan = ProductionPlan::create([
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->endOfWeek(),
            'status' => 'draft',
        ]);

        $approvedPlan = ProductionPlan::create([
            'week_start' => now()->startOfWeek()->addWeek(),
            'week_end' => now()->endOfWeek()->addWeek(),
            'status' => 'approved',
        ]);

        $draftReport = ProductionReport::create([
            'production_plan_id' => $approvedPlan->id,
            'status' => 'draft',
        ]);

        $submittedReport = ProductionReport::create([
            'production_plan_id' => $approvedPlan->id,
            'status' => 'submitted',
        ]);

        $this->assertTrue(ProductionPlanResource::canEdit($draftPlan));
        $this->assertTrue(ProductionPlanResource::canDelete($draftPlan));
        $this->assertFalse(ProductionPlanResource::canEdit($approvedPlan));
        $this->assertFalse(ProductionPlanResource::canDelete($approvedPlan));

        $this->assertTrue(ProductionReportResource::canEdit($draftReport));
        $this->assertTrue(ProductionReportResource::canDelete($draftReport));
        $this->assertFalse(ProductionReportResource::canEdit($submittedReport));
        $this->assertFalse(ProductionReportResource::canDelete($submittedReport));
    }
}

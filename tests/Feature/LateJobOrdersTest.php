<?php

namespace Tests\Feature;

use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\Proforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LateJobOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_scope_only_returns_open_job_orders_before_today(): void
    {
        config()->set('logging.default', 'errorlog');

        $partner = Partner::factory()->create();

        $lateJob = $this->makeJobOrder($partner->id, 'JO-LATE-001', now()->subDay()->toDateString(), 'active');
        $this->makeJobOrder($partner->id, 'JO-ONTIME-001', today()->toDateString(), 'active');
        $this->makeJobOrder($partner->id, 'JO-DONE-001', now()->subDays(3)->toDateString(), 'completed');
        $this->makeJobOrder($partner->id, 'JO-CANCELLED-001', now()->subDays(2)->toDateString(), 'cancelled');

        $lateJobIds = JobOrder::late()->pluck('id');

        $this->assertTrue($lateJobIds->contains($lateJob->id));
        $this->assertCount(1, $lateJobIds);
    }

    protected function makeJobOrder(int $partnerId, string $number, string $submissionDate, string $status): JobOrder
    {
        return JobOrder::create([
            'proforma_id' => Proforma::factory()->create(['partner_id' => $partnerId])->id,
            'job_order_number' => $number,
            'partner_id' => $partnerId,
            'job_type' => 'books',
            'services' => json_encode(['printing']),
            'submission_date' => $submissionDate,
            'remarks' => 'Late job order scope test',
            'advance_amount' => 0,
            'advance_paid' => false,
            'total' => 0,
            'status' => $status,
            'production_mode' => 'make_to_order',
        ]);
    }
}

<?php

namespace App\Services\JobOrders;

use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;

class DuplicateJobOrder
{
    public function handle(JobOrder $jobOrder): JobOrder
    {
        return DB::transaction(function () use ($jobOrder): JobOrder {
            $duplicate = new JobOrder($jobOrder->only($jobOrder->getFillable()));
            $duplicate->forceFill([
                'job_order_number' => null, 'submission_date' => today(), 'due_date' => today(),
                'proforma_id' => null, 'proforma_task_id' => null,
                'designer_id' => null, 'typist_id' => null, 'output_inventory_item_id' => null,
                'advance_paid' => false, 'advance_amount' => 0, 'status' => 'draft',
                'production_started_at' => null, 'materials_fully_issued_at' => null,
                'design_completed_at' => null, 'production_completed_at' => null, 'completed_at' => null, 'notified_late_at' => null,
            ]);
            $duplicate->save();

            return $duplicate->refresh();
        });
    }
}

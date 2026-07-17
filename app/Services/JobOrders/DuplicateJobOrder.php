<?php

namespace App\Services\JobOrders;

use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;

class DuplicateJobOrder
{
    public function handle(JobOrder $jobOrder): JobOrder
    {
        return DB::transaction(function () use ($jobOrder): JobOrder {
            $jobOrder->loadMissing('jobOrderTasks');

            $duplicate = new JobOrder($jobOrder->only($jobOrder->getFillable()));

            $duplicate->forceFill([
                'job_order_number' => null,
                'submission_date' => today(),
                'due_date' => today(),
                'advance_paid' => false,
                'advance_amount' => 0,
                'status' => 'draft',
                'production_started_at' => null,
                'materials_fully_issued_at' => null,
                'notified_late_at' => null,
            ]);

            $duplicate->save();

            foreach ($jobOrder->jobOrderTasks as $task) {
                $duplicateTask = $task->replicate([
                    'job_order_id',
                    'designer_id',
                    'typist_id',
                    'status',
                ]);

                $duplicateTask->forceFill([
                    'job_order_id' => $duplicate->id,
                    'designer_id' => null,
                    'typist_id' => null,
                    'status' => 'draft',
                ]);

                $duplicateTask->save();
            }

            return $duplicate->refresh();
        });
    }
}

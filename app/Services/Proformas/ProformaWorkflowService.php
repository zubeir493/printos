<?php

namespace App\Services\Proformas;

use App\Models\JobOrder;
use App\Models\Proforma;
use Illuminate\Support\Facades\DB;

class ProformaWorkflowService
{
    public function createJobOrder(Proforma $proforma): JobOrder
    {
        return DB::transaction(function () use ($proforma): JobOrder {
            $proforma->loadMissing('tasks');

            if (! $proforma->canCreateJobOrder()) {
                throw new \RuntimeException('Only approved proformas without a job order can create a job order.');
            }

            $jobOrder = JobOrder::create([
                'proforma_id' => $proforma->id,
                'partner_id' => $proforma->partner_id,
                'job_type' => $proforma->job_type,
                'production_mode' => $proforma->partner_id ? 'make_to_order' : 'make_to_stock',
                'services' => $proforma->services ?? [],
                'submission_date' => now(),
                'due_date' => $proforma->expiry_date,
                'remarks' => $proforma->remarks,
                'advance_amount' => 0,
                'advance_paid' => false,
                'subtotal' => $proforma->subtotal,
                'tax_amount' => $proforma->tax_amount,
                'total' => $proforma->total,
                'status' => 'draft',
            ]);

            foreach ($proforma->tasks as $task) {
                $jobOrder->jobOrderTasks()->create([
                    'name' => $task->name,
                    'quantity' => $task->quantity,
                    'size' => $task->size,
                    'task_cost' => $task->task_cost,
                    'paper' => $task->paper,
                    'deliverables' => $task->deliverables,
                    'instructions' => $task->instructions,
                    'status' => 'draft',
                ]);
            }

            $proforma->update(['status' => 'job_order_created']);

            return $jobOrder;
        });
    }
}

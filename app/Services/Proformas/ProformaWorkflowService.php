<?php

namespace App\Services\Proformas;

use App\Models\CostEstimate;
use App\Models\JobOrder;
use App\Models\Proforma;
use Illuminate\Support\Facades\DB;

class ProformaWorkflowService
{
    public function createFromEstimate(CostEstimate $estimate, array $data): Proforma
    {
        return DB::transaction(function () use ($estimate, $data): Proforma {
            $estimate->loadMissing('tasks');

            $proforma = Proforma::create([
                'cost_estimate_id' => $estimate->id,
                'partner_id' => $data['partner_id'],
                'job_type' => $estimate->job_type,
                'services' => $estimate->services,
                'issue_date' => $data['issue_date'] ?? now(),
                'expiry_date' => $data['expiry_date'],
                'remarks' => $data['remarks'] ?? $estimate->remarks,
                'subtotal' => $estimate->subtotal,
                'tax_amount' => $estimate->tax_amount,
                'total' => $estimate->total,
                'status' => 'draft',
                'email_recipient' => $data['email_recipient'] ?? null,
            ]);

            foreach ($estimate->tasks as $task) {
                $proforma->tasks()->create([
                    'name' => $task->name,
                    'quantity' => $task->quantity,
                    'size' => $task->size,
                    'unit_price' => $task->unit_price,
                    'task_cost' => $task->task_cost,
                    'paper' => $task->paper,
                    'deliverables' => $task->deliverables,
                    'instructions' => $task->instructions,
                ]);
            }

            $estimate->update(['status' => 'converted']);

            return $proforma;
        });
    }

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

<?php

namespace App\Services\Proformas;

use App\Models\JobOrder;
use App\Models\Proforma;
use App\Services\JobOrders\JobProductionCosting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProformaWorkflowService
{
    /** @return Collection<int, JobOrder> */
    public function createJobOrders(Proforma $proforma): Collection
    {
        return DB::transaction(function () use ($proforma): Collection {
            $locked = Proforma::query()->with(['tasks', 'costEstimate.lines'])->lockForUpdate()->findOrFail($proforma->id);
            $existing = $locked->jobOrders()->orderBy('id')->get();
            if ($existing->isNotEmpty()) {
                return $existing;
            }
            if (! $locked->partner_id) {
                throw ValidationException::withMessages(['proforma' => 'Select a customer before converting this quotation to a client job.']);
            }
            $subtotal = (int) round((float) $locked->subtotal * 100);
            $tax = (int) round((float) $locked->tax_amount * 100);
            $lineTotals = $locked->tasks->map(fn ($task): int => (int) round((float) $task->task_cost * 100));
            if (! $locked->canCreateJobOrder() || $locked->tasks->isEmpty() || $lineTotals->sum() !== $subtotal
                || $tax < 0 || (int) round((float) $locked->total * 100) !== $subtotal + $tax || ($subtotal === 0 && $tax !== 0)) {
                throw ValidationException::withMessages(['proforma' => 'Approve a nonempty quotation with consistent line prices, tax and total before conversion.']);
            }
            $jobs = new Collection;
            $allocatedTax = 0;
            $cumulativeSubtotal = 0;
            foreach ($locked->tasks as $index => $task) {
                if ($task->quantity < 1 || blank($task->name) || $lineTotals[$index] < 0) {
                    throw ValidationException::withMessages(['proforma' => 'Each quotation line needs a name, positive quantity and nonnegative price.']);
                }
                $cumulativeSubtotal += $lineTotals[$index];
                $cumulativeTax = $subtotal > 0 ? (int) round($tax * $cumulativeSubtotal / $subtotal) : 0;
                $lineTax = $cumulativeTax - $allocatedTax;
                $allocatedTax = $cumulativeTax;
                $estimate = $locked->tasks->count() === 1 && $locked->costEstimate?->quantity === $task->quantity ? $locked->costEstimate : null;
                $jobs->push(JobOrder::create([
                    'proforma_id' => $locked->id, 'proforma_task_id' => $task->id,
                    'uses_production_ticket' => true, 'production_due_date' => $locked->costEstimate?->deadline,
                    'cost_estimate_id' => $estimate?->id,
                    'estimated_production_costs' => $estimate ? app(JobProductionCosting::class)->estimateCosts($estimate) : null,
                    'name' => $task->name, 'quantity' => $task->quantity, 'size' => $task->size,
                    'paper' => $task->paper, 'deliverables' => $task->deliverables, 'instructions' => $task->instructions,
                    'cost_calc_file' => $locked->file_path,
                    'partner_id' => $locked->partner_id, 'job_type' => $locked->job_type,
                    'production_mode' => 'make_to_order',
                    'services' => $locked->services ?? [], 'submission_date' => today(), 'due_date' => null,
                    'remarks' => $locked->remarks, 'advance_amount' => 0, 'advance_paid' => false,
                    'subtotal' => $lineTotals[$index] / 100, 'tax_amount' => $lineTax / 100,
                    'total' => ($lineTotals[$index] + $lineTax) / 100, 'status' => 'draft',
                ]));
            }
            $locked->update(['status' => 'job_order_created']);

            return $jobs;
        });
    }
}

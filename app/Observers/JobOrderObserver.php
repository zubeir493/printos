<?php

namespace App\Observers;

use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobOrderObserver
{
    public function creating(JobOrder $jobOrder): void
    {
        if (! empty($jobOrder->job_order_number)) {
            return;
        }

        DB::transaction(function () use ($jobOrder): void {
            $last = JobOrder::lockForUpdate()->orderBy('id', 'desc')->first();
            $lastNumber = 0;
            if ($last && preg_match('/JO-(\d+)/', $last->job_order_number, $matches)) {
                $lastNumber = (int) $matches[1];
            }
            $jobOrder->job_order_number = 'JO-'.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        });
    }

    public function updating(JobOrder $jobOrder): void
    {
        if (! $jobOrder->isDirty('status')) {
            return;
        }

        if ($jobOrder->status !== 'production') {
            return;
        }

        if (! $jobOrder->canStartProduction()) {
            throw ValidationException::withMessages([
                'status' => 'Approve all artworks before moving this job order to production.',
            ]);
        }

        if (! $jobOrder->production_started_at) {
            $jobOrder->production_started_at = now();
        }
    }
}

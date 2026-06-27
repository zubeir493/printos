<?php

namespace App\Observers;

use App\Models\JobOrder;
use App\Services\Accounting\CreateSalesJournalEntry;
use App\Support\SequentialNumber;

class JobOrderObserver
{
    public function creating(JobOrder $jobOrder): void
    {
        if (! empty($jobOrder->job_order_number)) {
            return;
        }

        $jobOrder->job_order_number = SequentialNumber::next(
            lockName: 'job_orders',
            modelClass: JobOrder::class,
            column: 'job_order_number',
            prefix: 'JO-',
            padding: 6,
            likePattern: 'JO-%',
        );
    }

    public function updating(JobOrder $jobOrder): void
    {
        if (! $jobOrder->isDirty('status')) {
            return;
        }

        if ((string) $jobOrder->status !== 'active') {
            return;
        }

        if (! $jobOrder->production_started_at) {
            $jobOrder->production_started_at = now();
        }
    }

    public function updated(JobOrder $jobOrder): void
    {
        if (! $jobOrder->wasChanged('status')) {
            return;
        }

        if ((string) $jobOrder->status !== 'completed') {
            return;
        }

        app(CreateSalesJournalEntry::class)->handle($jobOrder);
    }
}

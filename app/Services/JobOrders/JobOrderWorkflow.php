<?php

namespace App\Services\JobOrders;

use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobOrderWorkflow
{
    public function transition(JobOrder $jobOrder, string $status): void
    {
        DB::transaction(function () use ($jobOrder, $status): void {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);
            if ((string) $locked->status === $status) {
                return;
            }
            if ($status === 'production' && filled($locked->deliverables) && ! $locked->isReadyForProduction()) {
                throw ValidationException::withMessages(['status' => 'Approve every required deliverable before production.']);
            }
            if ($status === 'completed' && ! $locked->canComplete()) {
                throw ValidationException::withMessages(['status' => 'Finish production and settle the client job balance before completion.']);
            }
            $locked->status->transitionTo($status);
        });
        $jobOrder->refresh();
    }

    public function sync(JobOrder $jobOrder): bool
    {
        $completed = DB::transaction(function () use ($jobOrder): bool {
            $locked = JobOrder::query()->lockForUpdate()->find($jobOrder->id);
            if (! $locked || (string) $locked->status === 'cancelled') {
                return false;
            }
            $status = (string) $locked->status;
            if ($status === 'completed') {
                if (! $locked->canComplete()) {
                    $locked->status->transitionTo('production');
                }

                return false;
            }
            if ($status === 'draft' && ($locked->designer_id || $locked->typist_id)) {
                $locked->status->transitionTo('design');
                $status = 'design';
            }
            if ($status === 'design' && $locked->isReadyForProduction()) {
                $locked->status->transitionTo('production');
                $status = 'production';
            }
            if ($status === 'production' && $locked->productionIsComplete()) {
                if (! $locked->production_completed_at) {
                    $locked->update(['production_completed_at' => now()]);
                }
                if ($locked->canComplete()) {
                    $locked->status->transitionTo('completed');

                    return true;
                }
            }

            return false;
        });
        $jobOrder->refresh();

        return $completed;
    }
}

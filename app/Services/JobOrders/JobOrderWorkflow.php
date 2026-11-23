<?php

namespace App\Services\JobOrders;

use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
            if (auth()->check()) {
                Gate::authorize('manageWorkflow', $locked);
            }
            if ($status === 'production' && ($issues = $locked->productionReleaseIssues()) !== []) {
                throw ValidationException::withMessages(['status' => $issues]);
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
            if (! $locked || (string) $locked->status === 'cancelled' || ((string) $locked->status === 'completed' && $locked->stock_received_at)) {
                return false;
            }
            $status = (string) $locked->status;
            if (! $locked->productionIsComplete() && $locked->production_completed_at) {
                $locked->updateQuietly(['production_completed_at' => null]);
            }
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
            if ($status === 'design' && ! $locked->uses_production_ticket && $locked->isReadyForProduction()) {
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

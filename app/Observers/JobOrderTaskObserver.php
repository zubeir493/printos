<?php

namespace App\Observers;

use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Notifications\DesignerAssignedToTask;
use App\Notifications\TypistAssignedToTask;

class JobOrderTaskObserver
{
    public function created(JobOrderTask $task): void
    {
        $this->notifyDesigner($task);
        $this->notifyTypist($task);
    }

    public function updated(JobOrderTask $task): void
    {
        if ($task->wasChanged('designer_id')) {
            $this->notifyDesigner($task);
        }

        if ($task->wasChanged('typist_id')) {
            $this->notifyTypist($task);
        }
    }

    public function saved(JobOrderTask $task): void
    {
        $this->syncJobOrderStatus($task->jobOrder);
    }

    public function deleted(JobOrderTask $task): void
    {
        $this->syncJobOrderStatus($task->jobOrder);
    }

    private function notifyDesigner(JobOrderTask $task): void
    {
        if (! $task->designer_id) {
            return;
        }

        $designer = $task->designer;

        if ($designer) {
            $designer->notify(new DesignerAssignedToTask($task));
        }
    }

    private function notifyTypist(JobOrderTask $task): void
    {
        if (! $task->typist_id) {
            return;
        }

        $typist = $task->typist;

        if ($typist) {
            $typist->notify(new TypistAssignedToTask($task));
        }
    }

    private function syncJobOrderStatus(?JobOrder $jobOrder): void
    {
        if ($jobOrder) {
            $jobOrder->recalculateTotals();
            $jobOrder->refresh()->syncCompletionStatus();
        }
    }
}

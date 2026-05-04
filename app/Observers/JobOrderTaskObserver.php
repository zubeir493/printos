<?php

namespace App\Observers;

use App\Models\JobOrderTask;
use App\Notifications\DesignerAssignedToTask;

class JobOrderTaskObserver
{
    public function created(JobOrderTask $task): void
    {
        $this->notifyDesigner($task);
    }

    public function updated(JobOrderTask $task): void
    {
        if ($task->wasChanged('designer_id')) {
            $this->notifyDesigner($task);
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

    private function syncJobOrderStatus(?\App\Models\JobOrder $jobOrder): void
    {
        if ($jobOrder) {
            $jobOrder->recalculateTotal();
        }
    }
}

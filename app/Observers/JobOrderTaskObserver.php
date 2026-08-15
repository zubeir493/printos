<?php

namespace App\Observers;

use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Notifications\DesignerAssignedToTask;
use App\Notifications\JobOrderCompletedNotification;
use App\Notifications\JobOrderTaskCancelledNotification;
use App\Notifications\ProductionTaskCompletedNotification;
use App\Notifications\TaskSentToProductionNotification;
use App\Notifications\TypistAssignedToTask;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

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

        if ($task->wasChanged('status')) {
            $this->notifyStatusChange($task);
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
            $originalStatus = (string) $jobOrder->status;

            $jobOrder->recalculateTotals();
            $jobOrder->refresh()->syncCompletionStatus();

            $jobOrder->refresh();

            if ($originalStatus !== 'completed' && (string) $jobOrder->status === 'completed') {
                $recipients = NotificationRecipients::roles(UserRole::Sales, UserRole::Finance, UserRole::Operations);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new JobOrderCompletedNotification($jobOrder));
                }
            }
        }
    }

    private function notifyStatusChange(JobOrderTask $task): void
    {
        if ($task->status === 'production') {
            $recipients = NotificationRecipients::roles(UserRole::Production);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new TaskSentToProductionNotification($task));
            }
        }

        if ($task->status === 'completed') {
            $recipients = NotificationRecipients::roles(UserRole::Operations, UserRole::Sales);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ProductionTaskCompletedNotification($task));
            }
        }

        if ($task->status === 'cancelled') {
            $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Operations, UserRole::Sales, UserRole::Production);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new JobOrderTaskCancelledNotification($task->loadMissing('jobOrder')));
            }
        }
    }
}

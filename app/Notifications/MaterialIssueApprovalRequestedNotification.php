<?php

namespace App\Notifications;

use App\Models\MaterialIssueApproval;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class MaterialIssueApprovalRequestedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected MaterialIssueApproval $approval) {}

    protected function webPushTitle(): string
    {
        return 'Material Over-Issue Approval Needed';
    }

    protected function webPushBody(): string
    {
        $this->approval->loadMissing(['materialRequest.inventoryItem', 'materialRequest.jobOrderTask.jobOrder', 'warehouse', 'requester']);

        return sprintf(
            '%s requested %.2f of %s from %s for %s.',
            $this->approval->requester?->name ?? 'A user',
            (float) $this->approval->quantity,
            $this->approval->materialRequest->inventoryItem->name,
            $this->approval->warehouse->name,
            $this->approval->materialRequest->jobOrderTask->jobOrder->job_order_number
        );
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'material-issue-approvals', 'index');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-shield-check')
            ->iconColor('warning')
            ->actions($this->databaseActions($notifiable, 'Review'))
            ->getDatabaseMessage();
    }
}

<?php

namespace App\Notifications;

use App\Models\MaterialIssueApproval;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class MaterialIssueDecisionNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected MaterialIssueApproval $approval) {}

    protected function webPushTitle(): string
    {
        return $this->approval->status === 'approved'
            ? 'Over-Issue Approved'
            : 'Over-Issue Rejected';
    }

    protected function webPushBody(): string
    {
        $isApproved = $this->approval->status === 'approved';
        $materialRequest = $this->approval->materialRequest;
        $jobOrderNumber = $materialRequest->jobOrderTask->jobOrder->job_order_number;
        $itemName = $materialRequest->inventoryItem->name;

        return $isApproved
            ? "Your request to issue {$this->approval->quantity} of {$itemName} for job {$jobOrderNumber} has been approved."
            : "Your request to issue {$this->approval->quantity} of {$itemName} for job {$jobOrderNumber} has been rejected."
            .($this->approval->decision_notes ? " Notes: {$this->approval->decision_notes}" : '');
    }

    public function toDatabase(object $notifiable): array
    {
        $isApproved = $this->approval->status === 'approved';

        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon($isApproved ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->iconColor($isApproved ? 'success' : 'danger')
            ->getDatabaseMessage();
    }
}

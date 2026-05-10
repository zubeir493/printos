<?php

namespace App\Notifications;

use App\Models\MaterialIssueApproval;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class MaterialIssueDecisionNotification extends Notification
{
    public function __construct(protected MaterialIssueApproval $approval) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isApproved = $this->approval->status === 'approved';
        $materialRequest = $this->approval->materialRequest;
        $jobOrderNumber = $materialRequest->jobOrderTask->jobOrder->job_order_number;
        $itemName = $materialRequest->inventoryItem->name;

        return FilamentNotification::make()
            ->title($isApproved ? 'Over-Issue Approved' : 'Over-Issue Rejected')
            ->body(
                $isApproved
                    ? "Your request to issue {$this->approval->quantity} of {$itemName} for job {$jobOrderNumber} has been approved."
                    : "Your request to issue {$this->approval->quantity} of {$itemName} for job {$jobOrderNumber} has been rejected."
                    .($this->approval->decision_notes ? " Notes: {$this->approval->decision_notes}" : '')
            )
            ->icon($isApproved ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->iconColor($isApproved ? 'success' : 'danger')
            ->getDatabaseMessage();
    }
}

<?php

namespace App\Observers;

use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestDecisionNotification;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class LeaveRequestObserver
{
    public function created(LeaveRequest $leaveRequest): void
    {
        $this->notifyHr($leaveRequest);
    }

    public function updated(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->wasChanged('status') && in_array($leaveRequest->status, ['approved', 'rejected'], true)) {
            $this->notifyHr($leaveRequest);
        }
    }

    private function notifyHr(LeaveRequest $leaveRequest): void
    {
        $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::HR);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new LeaveRequestDecisionNotification($leaveRequest->loadMissing(['employee', 'leaveType'])));
        }
    }
}

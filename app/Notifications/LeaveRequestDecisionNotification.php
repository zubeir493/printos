<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestDecisionNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $status = ucfirst($this->leaveRequest->status);

        return (new MailMessage)
            ->subject('Leave Request '.$status)
            ->line('Your leave request has been '.strtolower($status).'.')
            ->line('Dates: '.$this->leaveRequest->start_date->format('Y-m-d').' to '.$this->leaveRequest->end_date->format('Y-m-d'))
            ->action('View Request', url('/admin/leave-requests/'.$this->leaveRequest->id))
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->leaveRequest->status,
            'message' => 'Your leave request has been '.$this->leaveRequest->status.'.',
        ];
    }
}

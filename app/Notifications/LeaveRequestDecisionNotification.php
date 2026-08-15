<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class LeaveRequestDecisionNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via($notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    protected function webPushTitle(): string
    {
        return 'Leave Request '.ucfirst($this->leaveRequest->status);
    }

    protected function webPushBody(): string
    {
        return $this->message();
    }

    public function toMail($notifiable): MailMessage
    {
        $status = ucfirst($this->leaveRequest->status);

        return (new MailMessage)
            ->subject('Leave Request '.$status)
            ->line($this->message())
            ->line('Dates: '.$this->leaveRequest->start_date->format('Y-m-d').' to '.$this->leaveRequest->end_date->format('Y-m-d'))
            ->action('View Request', $this->notificationUrl($notifiable))
            ->line('Thank you for using our application!');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Leave Request '.ucfirst($this->leaveRequest->status))
            ->body($this->message())
            ->icon('heroicon-o-calendar-days')
            ->iconColor($this->leaveRequest->status === 'approved' ? 'success' : 'danger')
            ->actions($this->databaseActions($notifiable, 'Open request'))
            ->getDatabaseMessage();
    }

    public function toArray($notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->leaveRequest->status,
            'message' => $this->message(),
            'url' => $this->notificationUrl($notifiable),
        ];
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'leave-requests', 'index');
    }

    protected function message(): string
    {
        $employee = $this->leaveRequest->employee?->full_name ?? 'Employee';

        return "{$employee}'s leave request has been {$this->leaveRequest->status}.";
    }
}

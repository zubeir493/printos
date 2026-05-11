<?php

namespace App\Notifications;

use App\Models\JobOrder;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class JobOrderLateNotification extends Notification
{
    public function __construct(protected JobOrder $jobOrder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $partner = $this->jobOrder->partner?->name ?? 'Internal';
        $daysLate = now()->diffInDays($this->jobOrder->submission_date);

        return FilamentNotification::make()
            ->title('Job Order Past Due')
            ->body("Job order {$this->jobOrder->job_order_number} for {$partner} is {$daysLate} day(s) past its submission date and is still not completed.")
            ->icon('heroicon-o-clock')
            ->iconColor('warning')
            ->getDatabaseMessage();
    }
}

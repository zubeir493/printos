<?php

namespace App\Notifications;

use App\Models\SalesOrder;
use App\Support\Money;
use App\Notifications\Concerns\RoutesNotificationClicks;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalesOrderCreatedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use RoutesNotificationClicks;

    public function __construct(public SalesOrder $salesOrder) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Sales Order Created: ' . $this->salesOrder->order_number)
            ->line('A new sales order ' . $this->salesOrder->order_number . ' has been created.')
            ->line('Customer: ' . $this->salesOrder->partner?->name)
            ->line('Total: ' . number_format($this->salesOrder->total, 2) . ' Birr')
            ->action('View Sales Order', $this->notificationUrl($notifiable))
            ->line('Thank you for using our application!');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('New Sales Order Created')
            ->body('New Sales Order ' . $this->salesOrder->order_number . ' has been created.')
            ->icon('heroicon-o-shopping-cart')
            ->iconColor('primary')
            ->actions($this->databaseActions($notifiable, 'Open sales order'))
            ->getDatabaseMessage();
    }

    public function toArray($notifiable): array
    {
        return [
            'sales_order_id' => $this->salesOrder->id,
            'order_number' => $this->salesOrder->order_number,
            'customer_name' => $this->salesOrder->partner?->name,
            'total' => $this->salesOrder->total,
            'message' => 'New Sales Order ' . $this->salesOrder->order_number . ' has been created.',
            'url' => $this->notificationUrl($notifiable),
        ];
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'sales-orders', 'view', ['record' => $this->salesOrder]);
    }
}

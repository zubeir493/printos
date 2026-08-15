<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class PurchaseOrderApprovedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function via($notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    protected function webPushTitle(): string
    {
        return 'Purchase Order Approved';
    }

    protected function webPushBody(): string
    {
        return 'Purchase Order '.$this->purchaseOrder->po_number.' has been approved.';
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Purchase Order Approved: '.$this->purchaseOrder->po_number)
            ->line('The purchase order '.$this->purchaseOrder->po_number.' has been approved.')
            ->action('View Purchase Order', $this->notificationUrl($notifiable))
            ->line('Thank you for using our application!');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Purchase Order Approved')
            ->body('Purchase Order '.$this->purchaseOrder->po_number.' has been approved.')
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open purchase order'))
            ->getDatabaseMessage();
    }

    public function toArray($notifiable): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrder->id,
            'po_number' => $this->purchaseOrder->po_number,
            'message' => 'Purchase Order '.$this->purchaseOrder->po_number.' has been approved.',
            'url' => $this->notificationUrl($notifiable),
        ];
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'purchase-orders', 'view', ['record' => $this->purchaseOrder]);
    }
}

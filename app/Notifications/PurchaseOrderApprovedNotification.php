<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderApprovedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Purchase Order Approved: '.$this->purchaseOrder->po_number)
            ->line('The purchase order '.$this->purchaseOrder->po_number.' has been approved.')
            ->action('View Purchase Order', url('/admin/purchase-orders/'.$this->purchaseOrder->id))
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrder->id,
            'po_number' => $this->purchaseOrder->po_number,
            'message' => 'Purchase Order '.$this->purchaseOrder->po_number.' has been approved.',
        ];
    }
}

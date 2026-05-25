<?php

namespace App\Notifications;

use App\Models\GoodsReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoodsReceiptPostedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public GoodsReceipt $goodsReceipt) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Goods Receipt Posted: '.$this->goodsReceipt->receipt_number)
            ->line('The goods receipt '.$this->goodsReceipt->receipt_number.' has been posted.')
            ->line('Purchase Order: '.($this->goodsReceipt->purchaseOrder?->po_number ?? 'N/A'))
            ->line('Warehouse: '.($this->goodsReceipt->warehouse?->name ?? 'N/A'))
            ->action('View Goods Receipt', url('/admin/goods-receipts/'.$this->goodsReceipt->id))
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable): array
    {
        return [
            'goods_receipt_id' => $this->goodsReceipt->id,
            'receipt_number' => $this->goodsReceipt->receipt_number,
            'message' => 'Goods Receipt '.$this->goodsReceipt->receipt_number.' has been posted.',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Received: '.$this->payment->payment_number)
            ->line('A payment of '.number_format($this->payment->amount, 2).' Birr has been received.')
            ->line('Payment Number: '.$this->payment->payment_number)
            ->line('Customer: '.$this->payment->partner?->name)
            ->action('View Payment', url('/admin/payments/'.$this->payment->id))
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'payment_number' => $this->payment->payment_number,
            'amount' => $this->payment->amount,
            'customer_name' => $this->payment->partner?->name,
            'message' => 'Payment '.$this->payment->payment_number.' of '.number_format($this->payment->amount, 2).' Birr received.',
        ];
    }
}

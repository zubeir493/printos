<?php

namespace App\Mail;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Partner $partner) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Reminder from ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-reminder',
            with: [
                'partner' => $this->partner,
            ],
        );
    }
}

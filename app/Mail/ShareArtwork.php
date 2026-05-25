<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShareArtwork extends Mailable
{
    use Queueable, SerializesModels;

    public $artwork;

    public $recipientEmail;

    public $customMessage;

    public $subjectLine;

    /**
     * Create a new message instance.
     */
    public function __construct($artwork, $recipientEmail, $customMessage = null)
    {
        $this->artwork = method_exists($artwork, 'load')
            ? $artwork->load(['jobOrder', 'uploader'])
            : $artwork;

        if (is_array($recipientEmail)) {
            $this->recipientEmail = $recipientEmail['recipient_email'] ?? null;
            $this->customMessage = $recipientEmail['message'] ?? null;
            $this->subjectLine = $recipientEmail['subject'] ?? null;

            return;
        }

        $this->recipientEmail = $recipientEmail;
        $this->customMessage = $customMessage;
        $this->subjectLine = null;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine ?: 'Artwork Shared: '.basename($this->artwork->filename),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.share-artwork',
            with: [
                'artwork' => $this->artwork,
                'customMessage' => $this->customMessage,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

<?php

namespace App\Mail;

use App\Support\PrivateStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProformaGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $proformaData,
        public array $options = [],
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Proforma #'.($this->proformaData['proforma_data']['proforma_number'] ?? 'Document').' from '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.proforma-generated',
            with: [
                'proformaData' => $this->proformaData,
                'downloadUrl' => isset($this->proformaData['path'])
                    ? PrivateStorage::downloadUrl($this->proformaData['path'], now()->addDays(7))
                    : null,
                'customMessage' => $this->options['message'] ?? null,
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
        $filename = $this->proformaData['filename'] ?? 'proforma.pdf';

        if (isset($this->proformaData['pdf']) && method_exists($this->proformaData['pdf'], 'output')) {
            return [
                Attachment::fromData(fn () => $this->proformaData['pdf']->output(), $filename)
                    ->withMime('application/pdf'),
            ];
        }

        $path = $this->proformaData['path'] ?? null;

        if (! $path) {
            return [];
        }

        return [
            Attachment::fromData(function () use ($path) {
                try {
                    return Storage::disk(PrivateStorage::diskName())->get($path);
                } catch (Throwable $e) {
                    throw new RuntimeException('Proforma PDF could not be read from private storage: '.$e->getMessage(), 0, $e);
                }
            }, $filename)->withMime('application/pdf'),
        ];
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class InvoiceGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public array $invoiceData;
    public array $options;

    public function __construct(array $invoiceData, array $options = [])
    {
        $this->invoiceData = $invoiceData;
        $this->options = array_merge([
            'subject_prefix' => 'Document',
            'include_terms' => true,
        ], $options);
    }

    public function envelope(): Envelope
    {
        $invoiceNumber = $this->invoiceData['invoice_data']['invoice_number'] ?? 
                        $this->invoiceData['receipt_data']['receipt_number'] ?? 
                        'Document';
        
        $subject = $this->options['subject_prefix'] . " #{$invoiceNumber} from " . config('app.name');

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-generated',
            with: [
                'invoiceData' => $this->invoiceData,
                'companyInfo' => $this->invoiceData['invoice_data']['company_info'] ?? 
                               $this->invoiceData['receipt_data']['company_info'] ?? [],
                'options' => $this->options,
            ]
        );
    }

    public function attachments(): array
    {
        $filename = $this->invoiceData['filename'] ?? 'invoice.pdf';

        if (isset($this->invoiceData['pdf']) && method_exists($this->invoiceData['pdf'], 'output')) {
            return [
                Attachment::fromData(fn () => $this->invoiceData['pdf']->output(), $filename)
                    ->withMime('application/pdf'),
            ];
        }

        $path = $this->invoiceData['path'] ?? $this->invoiceData['file_path'] ?? null;

        if ($path) {
            return [
                Attachment::fromData(function () use ($path) {
                    try {
                        return Storage::disk('s3')->get($path);
                    } catch (Throwable $e) {
                        throw new RuntimeException('Invoice PDF could not be read from private storage: '.$e->getMessage(), 0, $e);
                    }
                }, $filename)->withMime('application/pdf'),
            ];
        }

        foreach (array_filter([
            $path ? storage_path("app/public/{$path}") : null,
            $path ? storage_path("app/{$path}") : null,
        ]) as $localPath) {
            if (is_file($localPath)) {
                return [
                    Attachment::fromPath($localPath)
                        ->as($filename)
                        ->withMime('application/pdf'),
                ];
            }
        }

        throw new RuntimeException("Invoice PDF could not be found for attachment: {$filename}");
    }
}

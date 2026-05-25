<?php

use App\Mail\InvoiceGenerated;
use App\Models\EmailLog;
use App\Services\InvoiceGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('logs invoice emails when sending succeeds', function () {
    Mail::fake();
    $beforeCount = EmailLog::query()->count();

    $service = app(InvoiceGeneratorService::class);

    $sent = $service->sendInvoiceEmail(
        [
            'filename' => 'invoice-SALES-2026-000001.pdf',
            'invoice_data' => [
                'invoice_number' => 'SALES-2026-000001',
                'message' => 'Please see attached invoice.',
            ],
            'pdf' => new class
            {
                public function output(): string
                {
                    return 'fake-pdf';
                }
            },
        ],
        'billing@example.com'
    );

    expect($sent)->toBeTrue();

    Mail::assertSent(InvoiceGenerated::class, function (InvoiceGenerated $mail): bool {
        return $mail->hasTo('billing@example.com');
    });

    expect(EmailLog::query()->count())->toBe($beforeCount + 1);

    $log = EmailLog::query()->latest('id')->firstOrFail();

    expect($log->recipient_email)->toBe('billing@example.com')
        ->and($log->subject)->toBe('Document #SALES-2026-000001')
        ->and($log->message)->toBe('Please see attached invoice.');
});

it('does not log invoice emails when sending fails', function () {
    Mail::shouldReceive('to')
        ->once()
        ->with('billing@example.com')
        ->andThrow(new RuntimeException('Mailtrap API request failed.'));

    $beforeCount = EmailLog::query()->count();
    $service = app(InvoiceGeneratorService::class);

    expect(fn () => $service->sendInvoiceEmail(
        [
            'filename' => 'invoice-SALES-2026-000002.pdf',
            'invoice_data' => [
                'invoice_number' => 'SALES-2026-000002',
            ],
            'pdf' => new class
            {
                public function output(): string
                {
                    return 'fake-pdf';
                }
            },
        ],
        'billing@example.com'
    ))->toThrow(RuntimeException::class, 'Mailtrap API request failed.');

    expect(EmailLog::query()->count())->toBe($beforeCount);
});

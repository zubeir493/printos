<?php

use App\Mail\InvoiceGenerated;
use Tests\TestCase;

uses(TestCase::class);

it('renders invoice mailable when company contact fields are missing', function () {
    $mailable = new InvoiceGenerated([
        'filename' => 'invoice-test.pdf',
        'pdf' => new class
        {
            public function output(): string
            {
                return 'test-pdf-content';
            }
        },
        'invoice_data' => [
            'invoice_number' => 'SALES-2026-000001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 100,
            'balance_due' => 100,
            'order' => (object) ['partner' => (object) ['name' => 'Acme']],
            'company_info' => [
                'name' => 'PrintOS',
            ],
        ],
    ]);

    $html = $mailable->render();

    expect($html)->toContain('PrintOS');
});

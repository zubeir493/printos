<?php

namespace App\Console\Commands;

use App\Mail\InvoiceGenerated;
use App\Mail\ShareArtwork;
use Illuminate\Console\Command;

class TestMailtrap extends Command
{
    protected $signature = 'mailtrap:test';

    protected $description = 'Test Mailtrap email integration';

    public function handle(): int
    {
        $this->info('Testing Mailtrap integration...');
        $this->line('');

        $this->info('1. Testing ShareArtwork email...');

        try {
            $mockArtwork = (object) [
                'filename' => 'test-artwork.pdf',
                'jobOrder' => (object) ['id' => 1, 'name' => 'Test Job'],
                'uploader' => (object) ['name' => 'Test User'],
            ];

            $shareArtwork = new ShareArtwork($mockArtwork, 'test@example.com', 'Test message');

            $this->info('[OK] ShareArtwork email created successfully');
            $this->line('  - Subject: '.$shareArtwork->envelope()->subject);
            $this->line('  - View: '.$shareArtwork->content()->view);
        } catch (\Throwable $e) {
            $this->error('[FAIL] ShareArtwork email test failed: '.$e->getMessage());
        }

        $this->line('');
        $this->info('2. Testing InvoiceGenerated email...');

        try {
            $mockInvoiceData = [
                'invoice_data' => [
                    'invoice_number' => 'INV-001',
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(30)->format('Y-m-d'),
                    'total_amount' => 100,
                    'balance_due' => 100,
                    'company_info' => ['name' => 'Test Company'],
                ],
                'filename' => 'invoice-001.pdf',
                'path' => 'invoices/invoice-001.pdf',
                'pdf' => new class
                {
                    public function output(): string
                    {
                        return '%PDF-1.4 test';
                    }
                },
            ];

            $invoiceGenerated = new InvoiceGenerated($mockInvoiceData);

            $this->info('[OK] InvoiceGenerated email created successfully');
            $this->line('  - Subject: '.$invoiceGenerated->envelope()->subject);
            $this->line('  - View: '.$invoiceGenerated->content()->view);
            $this->line('  - Attachments: '.count($invoiceGenerated->attachments()));
        } catch (\Throwable $e) {
            $this->error('[FAIL] InvoiceGenerated email test failed: '.$e->getMessage());
        }

        $this->line('');
        $this->info('3. Checking mail configuration...');

        try {
            $mailer = config('mail.default');
            $mailtrapConfig = config('mail.mailers.mailtrap');

            $this->info('[OK] Default mailer: '.$mailer);

            if ($mailtrapConfig) {
                $this->info('[OK] Mailtrap configuration found');
                $this->line('  - Transport: '.($mailtrapConfig['transport'] ?? 'Not set'));
                $this->line('  - API Key: '.($mailtrapConfig['api_key'] ? 'Set' : 'Not set'));
                $this->line('  - Endpoint: '.($mailtrapConfig['endpoint'] ?? 'Not set'));
            } else {
                $this->error('[FAIL] Mailtrap configuration not found');
            }
        } catch (\Throwable $e) {
            $this->error('[FAIL] Configuration check failed: '.$e->getMessage());
        }

        $this->line('');
        $this->info('Mailtrap integration test completed.');

        return self::SUCCESS;
    }
}

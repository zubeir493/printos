<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\ShareArtwork;
use App\Mail\InvoiceGenerated;

class TestMailtrap extends Command
{
    protected $signature = 'mailtrap:test';
    protected $description = 'Test Mailtrap email integration';

    public function handle()
    {
        $this->info('Testing Mailtrap Integration...');
        $this->line('');

        // Test 1: ShareArtwork email
        $this->info('1. Testing ShareArtwork email...');
        try {
            $mockArtwork = (object) [
                'filename' => 'test-artwork.pdf',
                'jobOrder' => (object) ['id' => 1, 'name' => 'Test Job'],
                'uploader' => (object) ['name' => 'Test User']
            ];
            
            $shareArtwork = new ShareArtwork($mockArtwork, 'test@example.com', 'Test message');
            
            $this->info('✓ ShareArtwork email created successfully');
            $this->line('  - Subject: ' . $shareArtwork->envelope()->subject);
            $this->line('  - View: ' . $shareArtwork->content()->view);
            
        } catch (\Exception $e) {
            $this->error('✗ ShareArtwork email test failed: ' . $e->getMessage());
        }

        $this->line('');

        // Test 2: InvoiceGenerated email
        $this->info('2. Testing InvoiceGenerated email...');
        try {
            $mockInvoiceData = [
                'invoice_data' => [
                    'invoice_number' => 'INV-001',
                    'company_info' => ['name' => 'Test Company']
                ],
                'filename' => 'invoice-001.pdf',
                'path' => 'invoices/invoice-001.pdf'
            ];
            
            $invoiceGenerated = new InvoiceGenerated($mockInvoiceData);
            
            $this->info('✓ InvoiceGenerated email created successfully');
            $this->line('  - Subject: ' . $invoiceGenerated->envelope()->subject);
            $this->line('  - View: ' . $invoiceGenerated->content()->view);
            $this->line('  - Attachments: ' . count($invoiceGenerated->attachments()));
            
        } catch (\Exception $e) {
            $this->error('✗ InvoiceGenerated email test failed: ' . $e->getMessage());
        }

        $this->line('');

        // Test 3: Check mail configuration
        $this->info('3. Checking mail configuration...');
        try {
            $mailer = config('mail.default');
            $this->info('✓ Default mailer: ' . $mailer);
            
            $mailtrapConfig = config('mail.mailers.mailtrap');
            if ($mailtrapConfig) {
                $this->info('✓ Mailtrap configuration found');
                $this->line('  - Transport: ' . ($mailtrapConfig['transport'] ?? 'Not set'));
                $this->line('  - API Key: ' . ($mailtrapConfig['api_key'] ? 'Set' : 'Not set'));
            } else {
                $this->error('✗ Mailtrap configuration not found');
            }
            
        } catch (\Exception $e) {
            $this->error('✗ Configuration check failed: ' . $e->getMessage());
        }

        $this->line('');
        $this->info('Mailtrap integration test completed.');
        
        return 0;
    }
}

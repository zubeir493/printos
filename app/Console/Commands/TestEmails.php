<?php

namespace App\Console\Commands;

use App\Mail\ShareArtwork;
use App\Models\Artwork;
use App\Models\SalesOrder;
use App\Services\InvoiceGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmails extends Command
{
    protected $signature = 'test:emails {type=all} {email?}';

    protected $description = 'Test email functionality with Mailtrap';

    public function handle()
    {
        $type = $this->argument('type');
        $email = $this->argument('email') ?? 'test@example.com';

        $this->info('Testing email functionality with Mailtrap...');
        $this->info("Recipient: {$email}");

        switch ($type) {
            case 'artwork':
                $this->testArtworkEmail($email);
                break;
            case 'invoice':
                $this->testInvoiceEmail($email);
                break;
            case 'all':
                $this->testArtworkEmail($email);
                $this->testInvoiceEmail($email);
                break;
            default:
                $this->error('Invalid type. Use: artwork, invoice, or all');

                return 1;
        }

        $this->info('Email test completed!');

        return 0;
    }

    private function testArtworkEmail($email)
    {
        $this->info("\n=== Testing Artwork Email ===");

        try {
            // Get a sample artwork
            $artwork = Artwork::first();

            if (! $artwork) {
                $this->error('No artwork found in database. Please create an artwork first.');

                return;
            }

            $this->info("Sending artwork: {$artwork->filename}");

            Mail::to($email)->send(new ShareArtwork($artwork, $email, 'This is a test artwork sharing email.'));

            $this->info('✅ Artwork email sent successfully!');

        } catch (\Exception $e) {
            $this->error('❌ Artwork email failed: '.$e->getMessage());
        }
    }

    private function testInvoiceEmail($email)
    {
        $this->info("\n=== Testing Invoice Email ===");

        try {
            // Get a sample sales order
            $salesOrder = SalesOrder::first();

            if (! $salesOrder) {
                $this->error('No sales order found in database. Please create a sales order first.');

                return;
            }

            $this->info("Generating invoice for Sales Order: {$salesOrder->order_number}");

            $invoiceService = app(InvoiceGeneratorService::class);
            $result = $invoiceService->generateFromSalesOrder($salesOrder);

            $this->info("Sending invoice: {$result['filename']}");

            $sent = $invoiceService->sendInvoiceEmail($result, $email);

            if ($sent) {
                $this->info('✅ Invoice email sent successfully!');
            } else {
                $this->error('❌ Invoice email failed to send');
            }

        } catch (\Exception $e) {
            $this->error('❌ Invoice email failed: '.$e->getMessage());
        }
    }
}

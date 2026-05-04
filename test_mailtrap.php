<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Mail;
use App\Mail\ShareArtwork;
use App\Mail\InvoiceGeneratorService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Mailtrap Integration...\n\n";

// Test 1: ShareArtwork email
echo "1. Testing ShareArtwork email...\n";
try {
    // Create a mock artwork object
    $mockArtwork = (object) [
        'filename' => 'test-artwork.pdf',
        'jobOrder' => (object) ['id' => 1, 'name' => 'Test Job'],
        'uploader' => (object) ['name' => 'Test User']
    ];
    
    $shareArtwork = new ShareArtwork($mockArtwork, 'test@example.com', 'Test message');
    
    // Set up test recipient
    $shareArtwork->to('test@example.com');
    
    echo "✓ ShareArtwork email created successfully\n";
    echo "  - Subject: " . $shareArtwork->envelope()->subject . "\n";
    echo "  - View: " . $shareArtwork->content()->view . "\n";
    
} catch (Exception $e) {
    echo "✗ ShareArtwork email test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: InvoiceGenerated email
echo "2. Testing InvoiceGenerated email...\n";
try {
    $mockInvoiceData = [
        'invoice_data' => [
            'invoice_number' => 'INV-001',
            'company_info' => ['name' => 'Test Company']
        ],
        'filename' => 'invoice-001.pdf',
        'path' => 'invoices/invoice-001.pdf'
    ];
    
    $invoiceGenerated = new \App\Mail\InvoiceGenerated($mockInvoiceData);
    
    // Set up test recipient
    $invoiceGenerated->to('test@example.com');
    
    echo "✓ InvoiceGenerated email created successfully\n";
    echo "  - Subject: " . $invoiceGenerated->envelope()->subject . "\n";
    echo "  - View: " . $invoiceGenerated->content()->view . "\n";
    echo "  - Attachments: " . count($invoiceGenerated->attachments()) . "\n";
    
} catch (Exception $e) {
    echo "✗ InvoiceGenerated email test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check mail configuration
echo "3. Checking mail configuration...\n";
try {
    $mailer = config('mail.default');
    echo "✓ Default mailer: " . $mailer . "\n";
    
    $mailtrapConfig = config('mail.mailers.mailtrap');
    if ($mailtrapConfig) {
        echo "✓ Mailtrap configuration found\n";
        echo "  - Transport: " . ($mailtrapConfig['transport'] ?? 'Not set') . "\n";
        echo "  - API Key: " . ($mailtrapConfig['api_key'] ? 'Set' : 'Not set') . "\n";
    } else {
        echo "✗ Mailtrap configuration not found\n";
    }
    
} catch (Exception $e) {
    echo "✗ Configuration check failed: " . $e->getMessage() . "\n";
}

echo "\nMailtrap integration test completed.\n";

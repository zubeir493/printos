<?php

// Simple test to check if our configuration is working
echo "Testing basic PHP execution...\n";
echo "Current working directory: " . __DIR__ . "\n";

// Check if composer autoload exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✓ Composer autoload found\n";
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo "✗ Composer autoload not found\n";
    exit(1);
}

// Check if our classes exist
if (class_exists('App\Mail\MailtrapTransport')) {
    echo "✓ MailtrapTransport class exists\n";
} else {
    echo "✗ MailtrapTransport class not found\n";
}

if (class_exists('App\Providers\MailtrapServiceProvider')) {
    echo "✓ MailtrapServiceProvider class exists\n";
} else {
    echo "✗ MailtrapServiceProvider class not found\n";
}

// Check if Mailtrap SDK is installed
if (class_exists('Mailtrap\MailtrapClient')) {
    echo "✓ Mailtrap SDK installed\n";
} else {
    echo "✗ Mailtrap SDK not installed\n";
}

echo "Basic test completed.\n";

# Mailtrap Integration Setup Guide

## Overview
This guide covers the complete setup of Mailtrap integration for your Laravel application to handle both artwork sharing and invoice sending functionality.

## ✅ Completed Steps

### 1. Mailtrap PHP SDK Installation
```bash
composer require mailtrap/mailtrap-php
```

### 2. Custom Mailtrap Transport
- Created `app/Mail/MailtrapTransport.php` - Custom transport class for Laravel
- Created `app/Providers/MailtrapServiceProvider.php` - Service provider to register the transport
- Registered the provider in `bootstrap/providers.php`

### 3. Configuration Updates
- Added Mailtrap configuration to `config/mail.php`
- Updated `.env.example` with Mailtrap environment variables
- Set default mailer to `mailtrap` in `.env.example`

## 🔄 Remaining Steps for You to Complete

### 1. Environment Configuration
Add these variables to your `.env` file:

```env
MAIL_MAILER=mailtrap
MAILTRAP_API_KEY=your_actual_mailtrap_api_key_here
MAIL_FROM_ADDRESS=your-email@yourdomain.com
MAIL_FROM_NAME=Your App Name
```

**To get your Mailtrap API Key:**
1. Go to https://mailtrap.io
2. Navigate to API Tokens section
3. Generate a new API token
4. Copy the token and add it to your `.env` file

### 2. Test the Integration
Run the following command to test the setup:

```bash
php artisan mailtrap:test
```

If the command doesn't work, try these alternatives:

```bash
# Clear caches first
php artisan config:clear
php artisan cache:clear

# Then test
php artisan mailtrap:test
```

### 3. Manual Testing
You can also test the email functionality manually:

#### Test ShareArtwork Email:
```php
// In any controller or route
use App\Mail\ShareArtwork;
use Illuminate\Support\Facades\Mail;

$artwork = Artwork::find(1); // Replace with actual artwork
Mail::to('test@example.com')->send(new ShareArtwork($artwork, 'test@example.com', 'Test message'));
```

#### Test InvoiceGenerated Email:
```php
// In any controller or route
use App\Mail\InvoiceGenerated;
use Illuminate\Support\Facades\Mail;

$invoiceData = [
    'invoice_data' => [
        'invoice_number' => 'INV-001',
        'company_info' => ['name' => 'Test Company']
    ],
    'filename' => 'invoice-001.pdf',
    'path' => 'invoices/invoice-001.pdf'
];

Mail::to('test@example.com')->send(new InvoiceGenerated($invoiceData));
```

## 📁 Files Created/Modified

### New Files:
- `app/Mail/MailtrapTransport.php` - Custom transport implementation
- `app/Providers/MailtrapServiceProvider.php` - Service provider
- `app/Console/Commands/TestMailtrap.php` - Test command
- `MAILTRAP_SETUP.md` - This setup guide

### Modified Files:
- `config/mail.php` - Added mailtrap configuration
- `bootstrap/providers.php` - Added MailtrapServiceProvider
- `.env.example` - Added mailtrap environment variables
- `composer.json` - Added mailtrap dependency (via composer install)

## 🔍 Verification Checklist

- [ ] Mailtrap PHP SDK installed via Composer
- [ ] `.env` file configured with Mailtrap API key
- [ ] Default mailer set to `mailtrap`
- [ ] Test command runs without errors
- [ ] ShareArtwork email sends successfully
- [ ] InvoiceGenerated email sends successfully
- [ ] Emails appear in Mailtrap dashboard

## 🚨 Troubleshooting

### Common Issues:

1. **"Class Mailtrap\MailtrapClient not found"**
   - Run: `composer install` or `composer update`
   - Check if `mailtrap/mailtrap-php` is in `composer.json`

2. **"Transport mailtrap not found"**
   - Clear config cache: `php artisan config:clear`
   - Ensure MailtrapServiceProvider is registered

3. **API Key Issues**
   - Verify API key is correct in `.env`
   - Check if API key has proper permissions in Mailtrap dashboard

4. **Email Not Sending**
   - Check Mailtrap dashboard for error logs
   - Verify email addresses are valid
   - Ensure email content is properly formatted

## 📧 Email Templates

The integration supports two main email types:

1. **ShareArtwork** (`app/Mail/ShareArtwork.php`)
   - Used for sharing artwork files
   - Template: `emails.share-artwork`
   - Includes artwork details and custom message

2. **InvoiceGenerated** (`app/Mail/InvoiceGenerated.php`)
   - Used for sending invoices and receipts
   - Template: `emails.invoice-generated`
   - Includes PDF attachment

## 🔄 Production vs Testing

### For Testing (Sandbox):
- Use your Mailtrap Sandbox API key
- Emails will appear in your Mailtrap inbox
- No actual emails sent to recipients

### For Production:
- Use your Mailtrap Sending API key
- Emails will be sent to actual recipients
- Monitor deliverability in Mailtrap dashboard

## 📞 Support

If you encounter issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify Mailtrap API status
3. Test with simple email first
4. Check environment variables are loaded correctly

---

**Next Steps:**
1. Add your Mailtrap API key to `.env`
2. Run the test command
3. Test both email types manually
4. Verify emails appear in Mailtrap dashboard

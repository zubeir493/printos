# Mailtrap Email Setup

This app sends invoice and artwork emails through the Mailtrap Sending API.

## Where To Put The Mailtrap API Key

Put your key in `.env`:

```env
MAIL_MAILER=mailtrap
MAILTRAP_API_KEY=your_actual_mailtrap_api_key_here
MAILTRAP_ENDPOINT=https://send.api.mailtrap.io/api/send
MAIL_FROM_ADDRESS=billing@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

After changing `.env`, clear cached config:

```bash
php artisan config:clear
```

## Smoke Test

Run:

```bash
php artisan mailtrap:test
```

That command checks:

- the artwork mailable can be built
- the invoice mailable can be built with a PDF attachment
- Laravel is configured to use the `mailtrap` mailer
- the API key and endpoint are loaded

## Files Involved

- `config/mail.php` defines the `mailtrap` mailer.
- `app/Providers/MailtrapServiceProvider.php` registers the custom mailer.
- `app/Mail/MailtrapApiTransport.php` posts messages to Mailtrap.
- `app/Mail/ShareArtwork.php` builds artwork emails.
- `app/Mail/InvoiceGenerated.php` builds invoice emails and attaches PDFs.
- `app/Services/InvoiceGeneratorService.php` sends generated invoices.
- `app/Filament/Resources/Artworks/Tables/ArtworksTable.php` sends artwork from the UI.
- `app/Filament/Resources/Invoices/Tables/InvoicesTable.php` sends/resends invoice emails from the UI.

## Notes

- Invoice PDFs are generated into S3, so invoice emails attach from the generated PDF object first and fall back to S3.
- Artwork emails include a temporary app-signed download link, which then streams the private S3 object.
- Set `APP_URL` to the real app URL before emailing external users. If it stays `http://localhost`, emailed private download links will point to localhost.
- On local Windows/Larabox installs that do not have a working CA bundle, set `AWS_VERIFY_SSL=false`. Use `AWS_VERIFY_SSL=true` in production.
- Use a Mailtrap Sending API token for real delivery. Use the endpoint above unless your Mailtrap account gives you a different one.

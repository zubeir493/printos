<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Artwork Shared</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { margin-bottom: 20px; }
        .download-btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .footer { font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">

        <div class="content">
            @if($customMessage)
                <div style="background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <strong>Message:</strong><br>
                    {{ $customMessage }}
                </div>
            @endif

                <li><strong>Filename:</strong> {{ basename($artwork->filename ?? '') }}</li>

            <a href="{{ \App\Support\PrivateStorage::downloadUrl($artwork->filename, now()->addDays(7)) }}" class="download-btn" target="_blank">
                📥 Download Artwork
            </a>

            <p><em>This secure download link will expire in 7 days for your protection.</em></p>
        </div>

        <div class="footer">
            <p>This email was sent from {{ config('app.name') }}. If you have any questions, please contact us.</p>
        </div>
    </div>
</body>
</html>

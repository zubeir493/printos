<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma</title>
    <style>
        body {
            background: #f4f5f7;
            color: #222222;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            margin: 0 auto;
            max-width: 600px;
            padding: 20px;
        }

        .header {
            background: #1f2933;
            color: #ffffff;
            margin-bottom: 20px;
            padding: 20px;
            text-align: center;
        }

        .content {
            background: #ffffff;
            border: 1px solid #d9dde3;
            padding: 20px;
        }

        h1 {
            font-size: 22px;
            margin: 0;
        }

        h2 {
            color: #1f2933;
            font-size: 20px;
            margin: 0 0 14px;
        }

        .message {
            background: #f7f8fa;
            border: 1px solid #e1e4e8;
            margin: 15px 0;
            padding: 14px;
        }

        .btn {
            background: #1f2933;
            color: #ffffff;
            display: inline-block;
            font-weight: bold;
            margin: 10px 0;
            padding: 12px 24px;
            text-decoration: none;
        }

        .hint {
            color: #5f6b7a;
            font-size: 12px;
        }
    </style>
</head>
<body>
    @php
        $proformaNumber = $proformaData['proforma_data']['proforma_number'] ?? '';
    @endphp

    <div class="header">
        <h1>{{ config('app.name') }}</h1>
    </div>

    <div class="content">
        <h2>Proforma {{ $proformaNumber }}</h2>

        <p>Hello,</p>

        <p>Please find the attached proforma for your records.</p>

        @if(filled($customMessage))
            <div class="message">{{ $customMessage }}</div>
        @endif

        @if($downloadUrl)
            <p><a href="{{ $downloadUrl }}" class="btn">Download proforma</a></p>
            <p class="hint">Link valid for 7 days. The proforma is also attached to this email.</p>
        @endif

        <p>Regards,<br>{{ config('app.name') }}</p>
    </div>
</body>
</html>

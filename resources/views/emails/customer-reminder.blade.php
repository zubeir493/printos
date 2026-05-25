<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827;">
    <p>Hello {{ $partner->name }},</p>

    <p>This is a reminder that an outstanding balance is still due on your account.</p>

    <p>Please contact our finance team if you need a copy of the invoice or would like to discuss payment options.</p>

    <p>Thank you,<br>{{ config('app.name') }}</p>
</body>
</html>

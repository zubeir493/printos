<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proforma</title>
</head>
<body>
    <p>Hello,</p>

    <p>Please find attached proforma {{ $proformaData['proforma_data']['proforma_number'] ?? '' }}.</p>

    @if(filled($customMessage))
        <p>{{ $customMessage }}</p>
    @endif

    @if($downloadUrl)
        <p><a href="{{ $downloadUrl }}">Download proforma</a></p>
    @endif

    <p>Thank you.</p>
</body>
</html>

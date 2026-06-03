<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $options['subject_prefix'] }} from {{ config('app.name') }}</title>
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

        .header h1 {
            font-size: 22px;
            margin: 0 0 6px;
        }

        .header p {
            margin: 0;
        }

        .content {
            background: #ffffff;
            border: 1px solid #d9dde3;
            margin-bottom: 20px;
            padding: 20px;
        }

        .content h2 {
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

        .download {
            margin: 20px 0;
            text-align: center;
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

        .hint,
        .footer {
            color: #5f6b7a;
            font-size: 12px;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
        }

        .invoice-details {
            background: #f7f8fa;
            border: 1px solid #e1e4e8;
            margin: 15px 0;
            padding: 15px;
        }

        .invoice-details h3 {
            color: #1f2933;
            font-size: 15px;
            margin: 0 0 8px;
        }

        .invoice-details table {
            border-collapse: collapse;
            width: 100%;
        }

        .invoice-details td {
            border-bottom: 1px solid #e1e4e8;
            padding: 8px 0;
        }

        .invoice-details tr:last-child td {
            border-bottom: 0;
        }

        .invoice-details td:last-child {
            font-weight: bold;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $companyName = $companyInfo['name'] ?? config('app.name');
        $companyAddress = $companyInfo['address'] ?? '';
        $companyPhone = $companyInfo['phone'] ?? '';
        $companyEmail = $companyInfo['email'] ?? '';
        $companyWebsite = $companyInfo['website'] ?? '';
        $contactLine = trim(implode(' | ', array_filter([$companyPhone, $companyEmail])));
        $documentNumber = $invoiceData['invoice_data']['invoice_number'] ?? $invoiceData['receipt_data']['receipt_number'];
        $recipientName = $invoiceData['invoice_data']['order']->partner->name ?? $invoiceData['receipt_data']['payment']->partner->name ?? 'Valued Customer';
    @endphp

    <div class="header">
        <h1>{{ $companyName }}</h1>
        @if($companyAddress !== '')
            <p>{{ $companyAddress }}</p>
        @endif
        @if($contactLine !== '')
            <p>{{ $contactLine }}</p>
        @endif
    </div>

    <div class="content">
        <h2>{{ $options['subject_prefix'] }} #{{ $documentNumber }}</h2>

        <p>Dear {{ $recipientName }},</p>

        <p>Please find attached your {{ strtolower($options['subject_prefix']) }} document for your records.</p>

        @if(isset($invoiceData['invoice_data']['message']) && ! empty($invoiceData['invoice_data']['message']))
            <div class="message">
                <strong>Message</strong><br>
                {{ $invoiceData['invoice_data']['message'] }}
            </div>
        @endif

        @if(isset($download_url))
            <div class="download">
                <a href="{{ $download_url }}" class="btn">Download document</a>
                <p class="hint">Link valid for 7 days. The document is also attached to this email.</p>
            </div>
        @endif

        @if(isset($invoiceData['invoice_data']))
            <div class="invoice-details">
                <h3>Invoice Details</h3>
                <table>
                    <tr>
                        <td>Invoice Number</td>
                        <td>{{ $invoiceData['invoice_data']['invoice_number'] }}</td>
                    </tr>
                    <tr>
                        <td>Invoice Date</td>
                        <td>{{ $invoiceData['invoice_data']['invoice_date'] }}</td>
                    </tr>
                    <tr>
                        <td>Due Date</td>
                        <td>{{ $invoiceData['invoice_data']['due_date'] }}</td>
                    </tr>
                    <tr>
                        <td>Total Amount</td>
                        <td>{{ \App\Support\Money::format($invoiceData['invoice_data']['total_amount']) }}</td>
                    </tr>
                    @if($invoiceData['invoice_data']['balance_due'] > 0)
                        <tr>
                            <td>Balance Due</td>
                            <td>{{ \App\Support\Money::format($invoiceData['invoice_data']['balance_due']) }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        @endif

        @if(isset($invoiceData['receipt_data']))
            <div class="invoice-details">
                <h3>Receipt Details</h3>
                <table>
                    <tr>
                        <td>Receipt Number</td>
                        <td>{{ $invoiceData['receipt_data']['receipt_number'] }}</td>
                    </tr>
                    <tr>
                        <td>Receipt Date</td>
                        <td>{{ $invoiceData['receipt_data']['receipt_date'] }}</td>
                    </tr>
                    <tr>
                        <td>Payment Method</td>
                        <td>{{ ucfirst($invoiceData['receipt_data']['payment']->method) }}</td>
                    </tr>
                    <tr>
                        <td>Amount Received</td>
                        <td>{{ \App\Support\Money::format($invoiceData['receipt_data']['payment']->amount) }}</td>
                    </tr>
                </table>
            </div>
        @endif

        <p>Please keep this document for your records.</p>

        @if(isset($invoiceData['invoice_data']['balance_due']) && $invoiceData['invoice_data']['balance_due'] > 0)
            <p>Payment is due by {{ $invoiceData['invoice_data']['due_date'] }}.</p>
        @endif

        <p>Regards,<br>{{ $companyName }}</p>
    </div>

    <div class="footer">
        <p>{{ trim(implode(' | ', array_filter([$companyName, $companyWebsite]))) }}</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $options['subject_prefix'] }} from {{ config('app.name') }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .content {
            background: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 10px 0;
        }
        .invoice-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .invoice-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-details td {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .invoice-details td:last-child {
            text-align: right;
            font-weight: bold;
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
        <h2>{{ $options['subject_prefix'] }} #{{ $invoiceData['invoice_data']['invoice_number'] ?? $invoiceData['receipt_data']['receipt_number'] }}</h2>
        
        <p>Dear {{ $invoiceData['invoice_data']['order']->partner->name ?? $invoiceData['receipt_data']['payment']->partner->name ?? 'Valued Customer' }},</p>
        
        <p>Please find attached your {{ strtolower($options['subject_prefix']) }} document for your records.</p>
        
        @if(isset($invoiceData['invoice_data']['message']) && !empty($invoiceData['invoice_data']['message']))
        <div style="background: #e3f2fd; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #2196f3;">
            <strong>Message:</strong><br>
            {{ $invoiceData['invoice_data']['message'] }}
        </div>
        @endif
        
        @if(isset($download_url))
        <div style="margin: 20px 0; text-align: center;">
            <a href="{{ $download_url }}" style="background-color: #2196f3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">
                📥 Download Document
            </a>
            <p style="font-size: 12px; color: #666; margin-top: 10px;">Link valid for 7 days. You can also find the document attached to this email.</p>
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
                    <td>{{ number_format($invoiceData['invoice_data']['total_amount'], 2) }}</td>
                </tr>
                @if($invoiceData['invoice_data']['balance_due'] > 0)
                <tr>
                    <td>Balance Due</td>
                    <td>{{ number_format($invoiceData['invoice_data']['balance_due'], 2) }}</td>
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
                    <td>{{ number_format($invoiceData['receipt_data']['payment']->amount, 2) }}</td>
                </tr>
            </table>
        </div>
        @endif
        
        <p><strong>Important Information:</strong></p>
        <ul>
            <li>Please keep this document for your records</li>
            @if(isset($invoiceData['invoice_data']['balance_due']) && $invoiceData['invoice_data']['balance_due'] > 0)
            <li>Payment is due by {{ $invoiceData['invoice_data']['due_date'] }}</li>
            @endif
            <li>If you have any questions, please contact our billing department</li>
        </ul>
        
        <p>If you have any questions or need assistance, please don't hesitate to contact us.</p>
        
        <p>Thank you for your business!</p>
        
        <p>Best regards,<br>
        The {{ $companyName }} Team</p>
    </div>

    <div class="footer">
        <p>{{ trim(implode(' | ', array_filter([$companyName, $companyWebsite]))) }}</p>
        <p>This email was sent automatically. Please do not reply to this email.</p>
    </div>
</body>
</html>

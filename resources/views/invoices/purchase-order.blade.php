<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceData['invoice_number'] }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #555;
            background: #F8F8F8;
            margin: 0;
            padding: 0;
        }
        .invoice-box {
            max-width: 800px;
            margin: 40px auto;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
            background: #fff;
        }
        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
        }
        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.top table td.title {
            font-size: 45px;
            line-height: 45px;
            color: #333;
        }
        .invoice-box table tr.information table td {
            padding-bottom: 40px;
        }
        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        .invoice-box table tr.details td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.item.last td {
            border-bottom: none;
        }
        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }
        .invoice-box .status-paid {
            background: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        .invoice-box .status-partial {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        .invoice-box .status-unpaid {
            background: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        .invoice-box .tax-breakdown {
            margin-top: 20px;
            font-size: 14px;
        }
        .invoice-box .terms {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        @media only screen and (max-width: 600px) {
            .invoice-box {
                width: 100%;
                margin: 0;
                padding: 20px;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <table>
            <tr class="top">
                <td colspan="4">
                    <table>
                        <tr>
                            <td class="title">
                                <img src="https://via.placeholder.com/100x50" style="width:100%; max-width:100px;">
                            </td>
                            <td>
                                <strong>Invoice #:</strong> {{ $invoiceData['invoice_number'] }}<br>
                                <strong>Invoice Date:</strong> {{ $invoiceData['invoice_date'] }}<br>
                                <strong>Due Date:</strong> {{ $invoiceData['due_date'] }}<br>
                                @if($invoiceData['status'])
                                    <strong>Status:</strong> 
                                    <span class="status-{{ strtolower($invoiceData['status']) }}">
                                        {{ strtoupper($invoiceData['status']) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="information">
                <td colspan="4">
                    <table>
                        <tr>
                            <td>
                                <strong>{{ $invoiceData['company_info']['name'] }}</strong><br>
                                {{ $invoiceData['company_info']['address'] }}<br>
                                {{ $invoiceData['company_info']['phone'] }}<br>
                                {{ $invoiceData['company_info']['email'] }}<br>
                                Tax ID: {{ $invoiceData['company_info']['tax_id'] }}
                            </td>
                            <td>
                                <strong>Bill To:</strong><br>
                                {{ $invoiceData['order']->partner->name }}<br>
                                {{ $invoiceData['order']->partner->address ?? '' }}<br>
                                {{ $invoiceData['order']->partner->phone ?? '' }}<br>
                                {{ $invoiceData['order']->partner->email ?? '' }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="heading">
                <td>Item</td>
                <td>Unit Price</td>
                <td>Quantity</td>
                <td>Total</td>
            </tr>
            
            @foreach($invoiceData['items'] as $item)
            <tr class="item {{ $loop->last ? 'last' : '' }}">
                <td>{{ $item->inventoryItem->name }}</td>
                <td>{{ number_format($item->unit_price, 2) }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
            
            <tr>
                <td colspan="4">
                    <table style="width: 300px; float: right;">
                        <tr class="total">
                            <td>Subtotal:</td>
                            <td>{{ number_format($invoiceData['subtotal'], 2) }}</td>
                        </tr>
                        
                        @if($invoiceData['options']['show_tax_breakdown'] && !empty($invoiceData['tax_calculations']['breakdown']))
                            @foreach($invoiceData['tax_calculations']['breakdown'] as $taxType => $amount)
                            <tr class="total">
                                <td>{{ $taxType }} ({{ ($this->taxConfig[$taxType] ?? 0) * 100 }}%):</td>
                                <td>{{ number_format($amount, 2) }}</td>
                            </tr>
                            @endforeach
                        @endif
                        
                        @if($invoiceData['tax_amount'] > 0)
                        <tr class="total">
                            <td>Total Tax:</td>
                            <td>{{ number_format($invoiceData['tax_amount'], 2) }}</td>
                        </tr>
                        @endif
                        
                        <tr class="total">
                            <td><strong>Total Amount:</strong></td>
                            <td><strong>{{ number_format($invoiceData['total_amount'], 2) }}</strong></td>
                        </tr>
                        
                        @if($invoiceData['options']['show_payment_status'])
                        <tr class="total">
                            <td>Paid Amount:</td>
                            <td>{{ number_format($invoiceData['order']->paid_amount, 2) }}</td>
                        </tr>
                        
                        <tr class="total">
                            <td><strong>Balance Due:</strong></td>
                            <td><strong>{{ number_format($invoiceData['balance_due'], 2) }}</strong></td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
            
            @if($invoiceData['options']['show_terms'])
            <tr>
                <td colspan="4" class="terms">
                    <strong>Terms & Conditions:</strong><br>
                    {!! nl2br(e($invoiceData['terms'] ?? "1. Payment is due within 30 days of invoice date.\n2. Late payments are subject to a 1.5% monthly interest charge.\n3. All prices are inclusive of applicable taxes unless otherwise stated.\n4. Goods remain the property of {$invoiceData['company_info']['name']} until paid in full.\n5. Please quote invoice number when making payment.")) !!}
                </td>
            </tr>
            @endif
        </table>
    </div>
</body>
</html>

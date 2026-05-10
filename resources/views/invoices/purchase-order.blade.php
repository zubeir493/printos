<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order Invoice {{ $invoiceData['invoice_number'] }}</title>
    @include('invoices._styles')
</head>
<body>
    {{-- Fixed terms footer --}}
    <div class="terms-footer">
        <strong>Terms & Conditions:</strong>
        {!! nl2br(e($invoiceData['terms'] ?? "1. Payment is due within 30 days of invoice date.\n2. Late payments are subject to a 1.5% monthly interest charge.\n3. All prices are inclusive of applicable taxes unless otherwise stated.\n4. Goods remain the property of {$invoiceData['company_info']['name']} until paid in full.\n5. Please quote invoice number when making payment.")) !!}
    </div>

    <div class="invoice-box">
        <table>
            <tr class="top">
                <td colspan="4">
                    <table>
                        <tr>
                            <td class="title">
                                @if(!empty($invoiceData['company_info']['logo']))
                                    <img src="{{ $invoiceData['company_info']['logo'] }}" style="width:100%; max-width:100px;">
                                @else
                                    <strong style="font-size:24px;">{{ $invoiceData['company_info']['name'] ?? config('app.name') }}</strong>
                                @endif
                            </td>
                            <td class="text-right">
                                <strong>PO Invoice #:</strong> {{ $invoiceData['invoice_number'] }}<br>
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
                                <strong>{{ $invoiceData['company_info']['name'] ?? config('app.name') }}</strong><br>
                                {{ $invoiceData['company_info']['address'] ?? '' }}<br>
                                {{ $invoiceData['company_info']['phone'] ?? '' }}<br>
                                {{ $invoiceData['company_info']['email'] ?? '' }}<br>
                                Tax ID: {{ $invoiceData['company_info']['tax_id'] ?? '' }}
                            </td>
                            <td class="text-right">
                                <strong>Supplier:</strong><br>
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
                <td class="text-right">Unit Price</td>
                <td class="text-right">Quantity</td>
                <td class="text-right">Total</td>
            </tr>

            @foreach($invoiceData['items'] as $item)
            <tr class="item {{ $loop->last ? 'last' : '' }}">
                <td>{{ $item->inventoryItem->name }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">{{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach

            <tr>
                <td colspan="4">
                    <table style="width: 300px; float: right;">
                        <tr class="total">
                            <td>Subtotal:</td>
                            <td class="text-right">{{ number_format($invoiceData['subtotal'], 2) }}</td>
                        </tr>
                        @if(!empty($invoiceData['options']['show_tax_breakdown']) && !empty($invoiceData['tax_calculations']['breakdown']))
                            @foreach($invoiceData['tax_calculations']['breakdown'] as $taxType => $amount)
                            <tr class="total">
                                <td>{{ $taxType }}:</td>
                                <td class="text-right">{{ number_format($amount, 2) }}</td>
                            </tr>
                            @endforeach
                        @elseif($invoiceData['tax_amount'] > 0)
                        <tr class="total">
                            <td>Tax:</td>
                            <td class="text-right">{{ number_format($invoiceData['tax_amount'], 2) }}</td>
                        </tr>
                        @endif
                        <tr class="total">
                            <td><strong>Total Amount:</strong></td>
                            <td class="text-right"><strong>{{ number_format($invoiceData['total_amount'], 2) }}</strong></td>
                        </tr>
                        @if(!empty($invoiceData['options']['show_payment_status']))
                        <tr class="total">
                            <td>Paid Amount:</td>
                            <td class="text-right">{{ number_format($invoiceData['order']->paid_amount, 2) }}</td>
                        </tr>
                        <tr class="total">
                            <td><strong>Balance Due:</strong></td>
                            <td class="text-right"><strong>{{ number_format($invoiceData['balance_due'], 2) }}</strong></td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

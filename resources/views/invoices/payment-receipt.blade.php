<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt_data['receipt_number'] }}</title>
    @include('invoices._styles')
</head>
<body>
    {{-- Fixed footer note --}}
    <div class="terms-footer">
        This receipt serves as official confirmation of payment received. Please retain for your records.
    </div>

    <div class="invoice-box">
        <table>
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">
                                @if(!empty($receipt_data['company_info']['logo_data_uri']) || !empty($receipt_data['company_info']['logo']))
                                    <img src="{{ $receipt_data['company_info']['logo_data_uri'] ?? $receipt_data['company_info']['logo'] }}" style="width:100%; max-width:100px;">
                                @else
                                    <strong style="font-size:24px;">{{ $receipt_data['company_info']['name'] ?? config('app.name') }}</strong>
                                @endif
                            </td>
                            <td class="text-right">
                                <strong>Receipt #:</strong> {{ $receipt_data['receipt_number'] }}<br>
                                <strong>Receipt Date:</strong> {{ $receipt_data['receipt_date'] }}<br>
                                <strong>Payment ID:</strong> {{ $receipt_data['payment']->payment_number }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                <strong>{{ $receipt_data['company_info']['name'] ?? config('app.name') }}</strong><br>
                                {{ $receipt_data['company_info']['address'] ?? '' }}<br>
                                {{ $receipt_data['company_info']['phone'] ?? '' }}<br>
                                {{ $receipt_data['company_info']['email'] ?? '' }}
                            </td>
                            <td class="text-right">
                                <strong>Received From:</strong><br>
                                {{ $receipt_data['payment']->partner->name }}<br>
                                {{ $receipt_data['payment']->partner->address ?? '' }}<br>
                                {{ $receipt_data['payment']->partner->phone ?? '' }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="heading">
                <td>Payment Details</td>
                <td class="text-right">Amount</td>
            </tr>

            <tr class="item">
                <td>
                    Payment Amount<br>
                    @if($receipt_data['options']['show_payment_method'])
                        Payment Method: {{ ucfirst($receipt_data['payment']->method) }}
                        @if($receipt_data['payment']->bank_id)
                            - {{ $receipt_data['payment']->bank->name }}
                        @endif
                        <br>
                    @endif
                    @if($receipt_data['payment']->reference)
                        Reference: {{ $receipt_data['payment']->reference }}
                    @endif
                </td>
                <td class="text-right">{{ \App\Support\Money::format($receipt_data['payment']->amount) }}</td>
            </tr>

            @if($receipt_data['options']['show_paid_document'] && $receipt_data['payable'])
            <tr class="heading">
                <td colspan="2">Paid Document</td>
            </tr>
            <tr class="item last">
                <td>
                    @if($receipt_data['payable'] instanceof \App\Models\SalesOrder)
                        Sales Order: {{ $receipt_data['payable']->order_number }}
                    @elseif($receipt_data['payable'] instanceof \App\Models\JobOrder)
                        Job Order: {{ $receipt_data['payable']->job_order_number }}
                    @elseif($receipt_data['payable'] instanceof \App\Models\PurchaseOrder)
                        Purchase Order: {{ $receipt_data['payable']->po_number }}
                    @else
                        {{ class_basename($receipt_data['payment']->payable_type) }} #{{ $receipt_data['payment']->payable_id }}
                    @endif
                </td>
                <td class="text-right">{{ \App\Support\Money::format($receipt_data['payment']->amount) }}</td>
            </tr>
            @endif

            <tr>
                <td colspan="2">
                    <table class="summary-table">
                        <tr class="total">
                            <td><strong>Total Received:</strong></td>
                            <td class="text-right"><strong>{{ \App\Support\Money::format($receipt_data['payment']->amount) }}</strong></td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <div class="paid-stamp">PAID</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

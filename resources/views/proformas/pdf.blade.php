<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proforma {{ $proformaData['proforma_number'] }}</title>
    @include('invoices._styles')
    <style>
        .proforma-header {
            text-align: center;
            margin-bottom: 18px;
        }

        .proforma-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .meta-table td {
            border: 0;
            padding: 2px 0;
        }

        .items-table {
            margin-top: 18px;
        }

        .items-table td,
        .items-table th {
            border: 1px solid #d7d7d7;
            padding: 8px;
        }

        .items-table th {
            background: #f4f4f4;
            font-weight: bold;
        }

        .totals-table {
            width: 310px;
            margin-left: auto;
            margin-top: 0;
        }

        .totals-table td {
            border: 1px solid #d7d7d7;
            padding: 8px;
        }

        .section-block {
            margin-top: 20px;
            line-height: 1.5;
        }

        .signature {
            margin-top: 44px;
        }
    </style>
</head>
<body>
    @php
        $company = $proformaData['company_info'];
        $customer = $proformaData['customer_info'];
    @endphp

    <div class="invoice-box">
        <div class="proforma-header">
            <div class="proforma-title">{{ $company['name'] ?? config('app.name') }}</div>
            <div>
                @if(filled($company['phone'] ?? null))
                    Tel: {{ $company['phone'] }}
                @endif
                @if(filled($company['email'] ?? null))
                    Email: {{ $company['email'] }}
                @endif
                @if(filled($company['tax_id'] ?? null))
                    TIN: {{ $company['tax_id'] }}
                @endif
            </div>
        </div>

        <table class="meta-table">
            <tr>
                <td></td>
                <td class="text-right"><strong>Date:</strong> {{ $proformaData['issue_date'] }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="text-right"><strong>No:</strong> {{ $proformaData['proforma_number'] }}</td>
            </tr>
        </table>

        <div class="section-block">
            <strong>To</strong><br>
            {{ $customer['name'] }}
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 8%;">s.n</th>
                    <th>Description</th>
                    <th style="width: 12%;">Unit</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 17%;">Unit Price</th>
                    <th style="width: 17%;">Total Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach($proformaData['items'] as $item)
                    <tr>
                        <td class="text-right">{{ $loop->iteration }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['unit'] }}</td>
                        <td class="text-right">{{ $item['quantity'] }}</td>
                        <td class="text-right">{{ \App\Support\Money::format($item['unit_price']) }}</td>
                        <td class="text-right">{{ \App\Support\Money::format($item['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td>Total</td>
                <td class="text-right">{{ \App\Support\Money::format($proformaData['subtotal']) }}</td>
            </tr>
            <tr>
                <td>VAT {{ rtrim(rtrim(number_format($proformaData['vat_rate'], 2), '0'), '.') }}%</td>
                <td class="text-right">{{ \App\Support\Money::format($proformaData['tax_amount']) }}</td>
            </tr>
            <tr>
                <td><strong>Total Price</strong></td>
                <td class="text-right"><strong>{{ \App\Support\Money::format($proformaData['total']) }}</strong></td>
            </tr>
        </table>

        <div class="section-block">
            <strong>Amount in words:</strong><br>
            {{ $proformaData['amount_in_words'] }}
        </div>

        <div class="section-block">
            <strong>Remark:</strong><br>
            @if(filled($proformaData['validity_days']))
                &bull; Validity period: {{ $proformaData['validity_days'] }} days<br>
            @endif
            &bull; Delivery period: Immediately after payment
            @if(filled($proformaData['remarks']))
                <br>{!! nl2br(e($proformaData['remarks'])) !!}
            @endif
        </div>

        @if(! empty($proformaData['bank_accounts']))
            <div class="section-block">
                <strong>Bank Account Numbers:</strong><br>
                @foreach($proformaData['bank_accounts'] as $bank)
                    {{ $bank['name'] }}: {{ $bank['account_number'] }}<br>
                @endforeach
            </div>
        @endif

        <div class="signature">
            ______________________________<br>
            General Manager
        </div>
    </div>
</body>
</html>

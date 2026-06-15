<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proforma {{ $proformaData['proforma_number'] }}</title>
    @include('invoices._styles')
    <style>
        .meta-table td {
            border: 0;
            padding: 2px 0;
        }

        .items-table {
            margin-top: 18px;
        }

        .items-table td,
        .items-table th {
            border-bottom: 1px solid #e1e4e8;
            padding: 8px 9px;
        }

        .items-table th {
            background: #1f2933;
            color: #ffffff;
            font-weight: bold;
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
        <table class="document-heading">
            <tr>
                <td>
                    @if(! empty($company['logo_data_uri']) || ! empty($company['logo']))
                        <img src="{{ $company['logo_data_uri'] ?? $company['logo'] }}" class="document-logo" alt="{{ $company['name'] ?? config('app.name') }}">
                    @endif
                    <h1 class="document-title">Proforma Invoice</h1>
                    <div class="document-subtitle">{{ $company['name'] ?? config('app.name') }}</div>
                </td>
                <td class="document-meta">
                    <strong>No:</strong> {{ $proformaData['proforma_number'] }}<br>
                    <strong>Date:</strong> {{ $proformaData['issue_date'] }}<br>
                    @if(filled($company['tax_id'] ?? null))
                        <strong>TIN:</strong> {{ $company['tax_id'] }}
                    @endif
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <td class="party-block">
                    <span class="party-label">From</span>
                    <strong>{{ $company['name'] ?? config('app.name') }}</strong><br>
                    @if(filled($company['phone'] ?? null))
                        {{ $company['phone'] }}<br>
                    @endif
                    @if(filled($company['email'] ?? null))
                        {{ $company['email'] }}
                    @endif
                </td>
                <td class="party-block">
                    <span class="party-label">To</span>
                    <strong>{{ $customer['name'] }}</strong>
                </td>
            </tr>
        </table>

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

        <table class="summary-table">
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
                - Validity period: {{ $proformaData['validity_days'] }} days<br>
            @endif
            - Delivery period: Immediately after payment
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
    </div>
</body>
</html>

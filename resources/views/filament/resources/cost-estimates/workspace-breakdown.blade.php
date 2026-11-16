<div class="estimator-table-wrap">
    <table class="estimator-table">
        <caption>Cost breakdown · {{ $result->formulaVersion }}</caption>
        <thead><tr><th>Cost item</th><th>Quantity</th><th>Unit cost</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($result->lines as $line)
                <tr><td>{{ $line['label'] }}<small>{{ $line['snapshot']['inventory']['name'] ?? $line['snapshot']['machine']['name'] ?? $line['category'] }} · {{ ($line['snapshot']['cost_source'] ?? '') === 'estimate_override' ? 'Estimate override' : ($line['category'] === 'Machine' ? 'All-inclusive rate' : 'Inventory average') }}</small></td><td>{{ number_format($line['quantity'], 4) }} {{ $line['unit'] }}</td><td>{{ \App\Support\Money::format($line['unit_cost'], 4, $result->settingsSnapshot['currency_code'] ?? null) }}</td><td>{{ \App\Support\Money::format($line['total'], 2, $result->settingsSnapshot['currency_code'] ?? null) }}</td></tr>
            @empty
                <tr><td colspan="4" class="estimator-table-empty">The breakdown appears as materials and machines are configured.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th colspan="3">Direct production cost</th><td>{{ \App\Support\Money::format($result->subtotal, 2, $result->settingsSnapshot['currency_code'] ?? null) }}</td></tr></tfoot>
    </table>
</div>

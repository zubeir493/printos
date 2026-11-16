<div class="estimator-table-wrap">
    <table class="estimator-table">
        <caption>Calculated machine time</caption>
        <thead><tr><th>Operation / machine</th><th>Setup</th><th>Running</th><th>Cost / hour</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($result->operations as $operation)
                <tr><td>{{ $operation['label'] }}<small>{{ $operation['machine_name'] }}</small></td><td>{{ number_format($operation['setup_hours'], 3) }} h</td><td>{{ number_format($operation['run_hours'], 3) }} h</td><td>{{ \App\Support\Money::format($operation['hourly_rate'], 2, $result->settingsSnapshot['currency_code'] ?? null) }}</td><td>{{ \App\Support\Money::format($operation['total'], 2, $result->settingsSnapshot['currency_code'] ?? null) }}</td></tr>
            @empty
                <tr><td colspan="5" class="estimator-table-empty">Choose verified machines to calculate setup and running time.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

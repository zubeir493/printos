<div class="estimator-table-wrap">
    <table class="estimator-table">
        <caption>Quantity comparison · selling prices exclude VAT</caption>
        <thead><tr><th>Quantity</th><th>Cost / piece</th><th>Sell / piece</th><th>Net selling price</th><th>Gross margin</th><th></th></tr></thead>
        <tbody>
            @forelse ($comparisons as $comparison)
                @php($price = $comparison['result'])
                <tr><td>{{ number_format($comparison['quantity']) }}</td><td>{{ \App\Support\Money::format(($price->subtotal + $price->overheadAmount) / $comparison['quantity'], 4, $price->settingsSnapshot['currency_code'] ?? null) }}</td><td>{{ $price->isComplete() ? \App\Support\Money::format($price->unitPrice, 4, $price->settingsSnapshot['currency_code'] ?? null) : 'Incomplete' }}</td><td>{{ $price->isComplete() ? \App\Support\Money::format($price->commercial['net_sell'], 2, $price->settingsSnapshot['currency_code'] ?? null) : '—' }}</td><td>{{ $price->isComplete() ? number_format($price->marginPercent, 2).'%' : '—' }}</td><td>
                    @if (empty($this->record) || $this->record->isEditable())
                        <x-filament::button size="xs" color="gray" wire:click="$set('data.quantity', {{ $comparison['quantity'] }})">Use quantity</x-filament::button>
                    @endif
                </td></tr>
            @empty
                <tr><td colspan="6" class="estimator-table-empty">Complete the base estimate to compare quantity breaks.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

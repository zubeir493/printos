@php
    $currency = $result->settingsSnapshot['currency_code'] ?? null;
    $money = fn ($amount, $precision = 2) => \App\Support\Money::format($amount, $precision, $currency);
    $ready = $result->isComplete();
@endphp
<x-filament::section class="estimator-summary-panel">
    <x-slot name="heading">Estimate summary</x-slot>
    <x-slot name="afterHeader"><x-filament::badge :color="$ready ? 'success' : 'warning'">{{ $ready ? 'Ready' : 'Incomplete' }}</x-filament::badge></x-slot>
    <div wire:loading.delay class="estimator-updating" role="status">Recalculating…</div>
    <div wire:loading.class="estimator-stale" class="estimator-summary-values">
        <div class="estimator-money-row"><span>{{ $ready ? 'Direct production cost' : 'Known costs so far' }}</span><strong>{{ $money($result->subtotal) }}</strong></div>
        <div class="estimator-money-row"><span>Additional overhead</span><strong>{{ $money($result->overheadAmount) }}</strong></div>
        <p class="estimator-caption">Machine rates include shop overhead.</p>
        <div class="estimator-money-row estimator-cost-total"><span>Full production cost</span><strong>{{ $money($result->subtotal + $result->overheadAmount) }}</strong></div>
        <div class="estimator-unit-price"><span>Unit selling price · excl. VAT</span><strong>{{ $ready ? $money($result->unitPrice, $result->unitPrice < 0.01 ? 6 : 4) : '—' }}</strong></div>
        <div class="estimator-money-row"><span>Net profit</span><strong>{{ $ready ? $money($result->commercial['net_profit'] ?? 0) : '—' }}</strong></div>
        <div class="estimator-money-row"><span>Achieved gross margin</span><strong>{{ $ready ? number_format($result->marginPercent, 2).'%' : '—' }}</strong></div>
        <div class="estimator-money-row"><span>Discount</span><strong>{{ $ready ? $money($result->discountAmount) : '—' }}</strong></div>
        <div class="estimator-money-row"><span>VAT ({{ number_format($result->vatRate, 2) }}%)</span><strong>{{ $ready ? $money($result->taxAmount) : '—' }}</strong></div>
        <div class="estimator-quote-total"><span>Final Price · incl. VAT</span><strong>{{ $ready ? $money($result->total) : 'Awaiting inputs' }}</strong></div>
    </div>
    @unless ($ready)
        <div class="estimator-readiness-note">Complete the missing inputs before finalizing. You can save this estimate as a draft.
            @foreach (array_slice($result->diagnostics, 0, 2) as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endunless
    @if ($result->materialConsumption !== [])
        <details class="estimator-detail"><summary>Material requirements <span>{{ count($result->materialConsumption) }}</span></summary>
            @foreach ($result->materialConsumption as $material)
                <div class="estimator-requirement"><span>{{ $material['name'] }}</span><strong>{{ rtrim(rtrim(number_format($material['quantity'], 6), '0'), '.') }} {{ $material['unit'] }}</strong>
                    @if (($material['shortage'] ?? 0) > 0)<small>Stock shortage: {{ number_format($material['shortage'], 4) }} {{ $material['unit'] }}. No stock is reserved.</small>@endif
                </div>
            @endforeach
        </details>
    @endif
    @if ($result->machineUsage !== [])
        <details class="estimator-detail"><summary>Machine hours <span>{{ count($result->machineUsage) }}</span></summary>
            @foreach ($result->machineUsage as $machine)
                <div class="estimator-requirement"><span>{{ $machine['name'] }}</span><strong>{{ number_format($machine['quantity'], 3) }} h</strong></div>
            @endforeach
        </details>
    @endif
</x-filament::section>

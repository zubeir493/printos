@php
    $editable = $configurator['editable'];
    $family = data_get($this->data, 'job_type', 'labels');
    $product = data_get($this->data, 'services.product', []);
    $commercial = data_get($this->data, 'services.commercial', []);
    $currency = $result->settingsSnapshot['currency_code'] ?? 'ETB';
    $money = fn ($value, $precision = 2) => \App\Support\Money::format($value, $precision, $currency);
@endphp
<div class="estimator-configurator">
    <section class="estimator-config-overview" aria-labelledby="estimate-overview-heading">
        <div class="estimator-config-toolbar"><div><span class="estimator-eyebrow">YOUR ESTIMATE</span><h2 id="estimate-overview-heading">{{ data_get($this->data, 'description') ?: $configurator['preset']['name'] }}</h2><p>{{ number_format((int) data_get($this->data, 'quantity', 0)) }} pieces · {{ $product['width_mm'] ?? '—' }} × {{ $product['height_mm'] ?? '—' }} mm @if ($family === 'books') · {{ $product['page_count'] ?? '—' }} pages @endif</p></div><div class="flex flex-wrap gap-2"><x-filament::button color="gray" icon="heroicon-o-adjustments-horizontal" wire:click="$set('estimatorTab', 'materials')">Production details</x-filament::button>@if ($editable)<x-filament::button type="submit" :key-bindings="['mod+s']" wire:loading.attr="disabled">Save estimate</x-filament::button>@endif</div></div>

        <section class="estimator-price-strip" aria-label="Live estimate" aria-live="polite">
            <div wire:loading.delay class="estimator-updating" role="status">Recalculating…</div>
            <div class="estimator-price-metrics" wire:loading.class="estimator-stale">
                <div class="estimator-primary-price"><span>Selling price · incl. VAT</span><strong>{{ $result->isComplete() ? $money($result->total) : 'Awaiting review' }}</strong><small>{{ $result->isComplete() ? 'Ready to save and finalize' : 'Save a draft while completing the remaining inputs' }}</small></div>
                <div><span>Per piece · excl. VAT</span><strong>{{ $result->isComplete() ? $money($result->unitPrice, 4) : '—' }}</strong></div>
                <div><span>{{ $result->isComplete() ? 'Production cost' : 'Known costs so far' }}</span><strong>{{ $money($result->subtotal + $result->overheadAmount) }}</strong></div>
                <div><span>Gross margin</span><strong>{{ $result->isComplete() ? number_format($result->marginPercent, 1).'%' : '—' }}</strong></div>
            </div>
            <div class="estimator-price-links"><span class="estimator-caption">Machine rates include shop overhead.</span><button type="button" class="estimator-text-action" wire:click="$set('estimatorTab', 'pricing')">Cost breakdown</button><button type="button" class="estimator-text-action" wire:click="$set('estimatorTab', 'quantities')">Compare quantities</button></div>
        </section>

        @include('filament.resources.cost-estimates.workspace-readiness', ['actionable' => true])
    </section>

    <aside class="estimator-config-sidebar">
        <div class="estimator-config-heading"><span class="estimator-eyebrow">PRODUCT CONFIGURATOR</span><h2>What are we printing?</h2></div>
        <button type="button" class="estimator-product-trigger" x-on:click="$dispatch('open-modal', { id: 'estimator-product-picker' })" @disabled(! $editable) aria-haspopup="dialog">
            <x-filament::icon :icon="'heroicon-o-'.$configurator['preset']['icon']" class="size-6" />
            <span><strong>{{ $configurator['preset']['name'] }}</strong><small>Change product</small></span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4" />
        </button>

        <x-estimator-input path="data.description" label="Product name" type="text" :disabled="! $editable" />
        <x-estimator-input path="data.quantity" label="Quantity" min="1" max="100000000" step="1" suffix="pieces" :disabled="! $editable" />
        <div class="estimator-config-pair">
            <x-estimator-input path="data.services.product.width_mm" label="Finished width" min="1" suffix="mm" :disabled="! $editable" />
            <x-estimator-input path="data.services.product.height_mm" label="Finished height" min="1" suffix="mm" :disabled="! $editable" />
        </div>
        @if ($family === 'books')
            <x-estimator-input path="data.services.product.page_count" label="Text pages (excludes cover)" min="4" max="10000" step="4" :disabled="! $editable" />
        @elseif (in_array($family, ['labels', 'stickers'], true))
            <x-estimator-input path="data.services.product.shape" label="Shape" type="select" :options="['rectangle' => 'Rectangle', 'round' => 'Round', 'oval' => 'Oval', 'custom' => 'Custom']" :disabled="! $editable" />
        @else
            <button type="button" class="estimator-text-action" wire:click="$set('estimatorTab', 'materials')">Choose dieline &amp; blank dimensions <span aria-hidden="true">↗</span></button>
        @endif

        <div class="estimator-config-divider"><h3>Paper &amp; printing</h3></div>
        @foreach ($configurator['components'] as $key => $materialRow)
            @php($materialLabel = match ($materialRow['role'] ?? 'stock') {
                'stock' => match ($family) { 'packages' => 'Carton board', 'stickers' => 'Sticker stock', default => 'Label paper' },
                'text' => 'Text paper', 'cover' => 'Cover paper', 'case' => 'Case wrap paper',
                default => \App\Filament\Resources\CostEstimates\Schemas\EstimatorWorkspace::ROLES[$materialRow['role']] ?? 'Paper / board',
            })
            <div class="estimator-config-material" wire:key="quick-material-{{ $key }}">
                <x-estimator-input :path="'data.services.components.'.$key.'.material_id'" :label="$materialLabel" type="select" :options="['' => 'Choose from inventory'] + $configurator['materials']" :disabled="! $editable" />
                <div class="estimator-config-pair">
                    <x-estimator-input :path="'data.services.components.'.$key.'.front_colors'" label="Front" type="select" :options="[0 => 'Unprinted', 1 => '1 colour', 2 => '2 colours', 4 => 'Full colour', 6 => '6 colours', 8 => '8 colours', 12 => '12 colours'] + (isset($materialRow['front_colors']) ? [$materialRow['front_colors'] => $materialRow['front_colors'].' colours'] : [])" :disabled="! $editable" />
                    <x-estimator-input :path="'data.services.components.'.$key.'.back_colors'" label="Back" type="select" :options="[0 => 'Unprinted', 1 => '1 colour', 2 => '2 colours', 4 => 'Full colour', 6 => '6 colours', 8 => '8 colours', 12 => '12 colours'] + (isset($materialRow['back_colors']) ? [$materialRow['back_colors'] => $materialRow['back_colors'].' colours'] : [])" :disabled="! $editable" />
                </div>
            </div>
        @endforeach

        <div class="estimator-config-divider"><h3>Finishing</h3><button type="button" class="estimator-text-action" x-on:click="$dispatch('open-modal', { id: 'estimator-finish-picker' })" @disabled(! $editable)>+ Add finish</button></div>
        <p class="estimator-caption">The product prepares its required route. Add optional finishes here.</p>
        <div class="estimator-finish-tags">
            @foreach ($configurator['route'] as $step)
                <button type="button" wire:click="$set('estimatorTab', 'production')">{{ $step['label'] }}</button>
            @endforeach
        </div>
        <x-estimator-input path="data.services.commercial.profit_percent" :label="($commercial['pricing_mode'] ?? 'margin') === 'markup' ? 'Target markup' : 'Target gross margin'" suffix="%" :max="($commercial['pricing_mode'] ?? 'margin') === 'markup' ? 1000 : 99" :disabled="! $editable" />
        <button type="button" class="estimator-text-action" wire:click="$set('estimatorTab', 'pricing')">Discounts, overhead &amp; pricing settings <span aria-hidden="true">↗</span></button>
        <details class="estimator-detail"><summary>Customer</summary><x-estimator-input path="data.partner_id" label="Customer (optional)" type="select" :options="['' => 'No customer'] + $configurator['customers']" :disabled="! $editable" /></details>
    </aside>

    <div class="estimator-config-main">
        <section class="estimator-production-story" aria-label="Production plan"><div class="estimator-config-divider"><h3>How it will be made</h3><button type="button" class="estimator-text-action" wire:click="$set('estimatorTab', 'production')">Edit route</button></div><div class="estimator-route-flow"><button type="button" wire:click="$set('estimatorTab', 'materials')"><strong>Print</strong><small>Paper, press &amp; layout</small></button>@foreach ($configurator['route'] as $step)<span aria-hidden="true">→</span><button type="button" wire:click="$set('estimatorTab', 'production')"><strong>{{ $step['label'] }}</strong><small>{{ $step['machine'] ? 'Machine selected' : 'Choose machine' }}</small></button>@endforeach</div></section>

        <div class="estimator-config-divider"><h3>Sheet preview</h3><button type="button" class="estimator-text-action" wire:click="$set('estimatorTab', 'materials')">Review layouts</button></div>
        @include('filament.resources.cost-estimates.workspace-layouts', ['compact' => true])
        @if ($result->materialConsumption !== [])
            <details class="estimator-detail"><summary>Material requirements &amp; stock availability</summary>
                @foreach ($result->materialConsumption as $material)
                    <div class="estimator-requirement"><span>{{ $material['name'] }}</span><strong>{{ number_format($material['quantity'], 4) }} {{ $material['unit'] }}</strong>@if (($material['shortage'] ?? 0) > 0)<small>Stock shortage: {{ number_format($material['shortage'], 4) }} {{ $material['unit'] }}. No stock is reserved.</small>@endif</div>
                @endforeach
            </details>
        @endif
    </div>

    <x-filament::modal id="estimator-product-picker" width="4xl" heading="Choose a product" description="Start with a production recipe. Your existing selections are kept for review when changing products.">
        <div class="estimator-product-grid">
            @foreach ($configurator['products'] as $key => $preset)
                <button type="button" wire:click="selectEstimatorProduct('{{ $key }}')" wire:loading.attr="disabled" @disabled(! $editable) aria-pressed="{{ $configurator['key'] === $key ? 'true' : 'false' }}">
                    <span class="estimator-product-illustration"><x-filament::icon :icon="'heroicon-o-'.$preset['icon']" class="size-12" /></span><strong>{{ $preset['name'] }}</strong><small>{{ $preset['description'] }}</small>
                </button>
            @endforeach
        </div>
    </x-filament::modal>
    <x-filament::modal id="estimator-finish-picker" width="md" heading="Add a finish" description="The machine and required consumable are prepared where there is one configured match. Review consumption before quoting.">
        <div class="estimator-finish-picker">
            @foreach (['laminating' => 'Lamination · one side', 'foiling' => 'Foil stamping', 'embossing' => 'Embossing', 'coating' => 'Coating / varnishing'] as $operation => $label)
                <x-filament::button color="gray" wire:click="addEstimatorFinish('{{ $operation }}')" wire:loading.attr="disabled" :disabled="! $editable">{{ $label }}</x-filament::button>
            @endforeach
        </div>
    </x-filament::modal>
</div>

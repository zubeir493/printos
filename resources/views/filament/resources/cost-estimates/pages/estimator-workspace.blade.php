<x-filament-panels::page full-height>
    <div class="estimator-canvas-shell">
        <div class="estimator-canvas-content">
            @if (($this->record ?? null) && $this->record->formula_version !== 'sheetfed-v2')
                <x-filament::button tag="a" :href="$this->backUrl()" color="gray" icon="heroicon-o-arrow-left" class="mb-4">
                    {{ $this->backLabel() }}
                </x-filament::button>
            @endif
            {{ $this->content }}
        </div>
    </div>
</x-filament-panels::page>

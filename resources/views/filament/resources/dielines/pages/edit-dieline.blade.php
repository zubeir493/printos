<x-filament-panels::page full-height>
    @php($imposition = $this->currentImposition())
    <form wire:submit="save" class="dieline-canvas-shell" @if ($imposition && ! $this->impositionNotified) wire:poll.3s.keep-alive="checkImposition" @endif>
        <aside class="dieline-editor-sidebar">
            <div class="dieline-editor-sidebar-scroll">
                <a href="{{ $this->backUrl() }}" class="mb-4 inline-flex w-full items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                    <svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="m11.5 4.5-5 5.5 5 5.5M7 10h8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                    <span>{{ $this->backLabel() }}</span>
                </a>

                <div class="dieline-editor-field">
                    <span id="dieline-type-label">Dieline type <sup>*</sup></span>
                    <button type="button" class="dieline-template-trigger" aria-labelledby="dieline-type-label dieline-current-template" aria-haspopup="dialog" x-on:click="$dispatch('open-modal', { id: 'dieline-template-picker' })">
                        <span><strong id="dieline-current-template">{{ $this->templateOptions()[data_get($this->data, 'template_key')] ?? 'Choose a blueprint' }}</strong></span>
                        <svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="m7 4 6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
                    </button>
                    @error('data.template_key')
                        <small>{{ $message }}</small>
                    @enderror
                </div>

                <div class="dieline-editor-fields">
                    @foreach ($this->dimensionFields() as $field)
                        <label class="dieline-editor-field" wire:key="dieline-field-{{ $field['key'] }}">
                            <span>{{ $field['label'] }} <sup>*</sup></span>
                            <x-filament::input.wrapper
                                :suffix="$field['suffix']"
                                class="fi-fo-text-input"
                                x-on:focus-input.stop="$el.querySelector('input')?.focus()"
                            >
                                <input
                                    type="number"
                                    step="{{ $field['step'] }}"
                                    min="{{ $field['min'] }}"
                                    @if (isset($field['max'])) max="{{ $field['max'] }}" @endif
                                    wire:model.live.debounce.300ms="data.dimensions.{{ $field['key'] }}"
                                    class="fi-input"
                                />
                            </x-filament::input.wrapper>
                            @error("data.dimensions.{$field['key']}")
                                <small>{{ $message }}</small>
                            @enderror
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="dieline-editor-actions" x-data="{ open: false }" x-on:click.outside="open = false">
                <button type="submit" class="dieline-save-action">
                    <span>Save Dieline</span>
                </button>

                <button type="button" class="dieline-save-menu" x-on:click="open = ! open" aria-label="Dieline actions" x-bind:aria-expanded="open" aria-haspopup="true">
                    <svg viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M5.2 7.7a1 1 0 0 1 1.4 0L10 11.1l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 9.1a1 1 0 0 1 0-1.4Z" />
                    </svg>
                </button>

                <div class="dieline-download-menu" x-cloak x-show="open" x-transition.origin.bottom.left>
                    <button type="button" wire:click="refreshImpositionPreview" x-on:click="open = false; $dispatch('open-modal', { id: 'dieline-imposition-picker' })">Impose on sheet</button>
                    <button type="button" wire:click="downloadInstantSvg" x-on:click="open = false">Download SVG</button>
                    <button type="button" wire:click="downloadInstantPdf" x-on:click="open = false">Download PDF</button>
                    <button type="button" wire:click="downloadInstantDxf" x-on:click="open = false">Download DXF</button>
                </div>
            </div>
        </aside>

        <main class="dieline-editor-canvas">
            {!! $this->preview() !!}
        </main>
    </form>

    <x-filament::modal id="dieline-imposition-picker" width="md" heading="Impose on sheet">
        <form wire:submit="startImposition" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                @foreach (['width' => 'Sheet width', 'height' => 'Sheet height', 'horizontal_spacing' => 'Horizontal spacing', 'vertical_spacing' => 'Vertical spacing', 'minimum_parallel_clearance' => 'Minimum cut-line distance'] as $key => $label)
                    <label @class(['block space-y-2', 'col-span-2' => $key === 'minimum_parallel_clearance']) for="imposition-{{ $key }}">
                        <span class="text-sm font-medium">{{ $label }}</span>
                        <x-filament::input.wrapper suffix="mm">
                            <x-filament::input id="imposition-{{ $key }}" type="number" :min="in_array($key, ['width', 'height']) ? 1 : 0" :max="in_array($key, ['width', 'height']) ? 5000 : 100" :step="in_array($key, ['width', 'height']) ? 1 : 0.01" wire:model.live.debounce.600ms="impositionSettings.{{ $key }}" required />
                        </x-filament::input.wrapper>
                        @error('impositionSettings.'.$key) <span class="text-xs text-danger-600">{{ $message }}</span> @enderror
                    </label>
                @endforeach
            </div>

            <div class="rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800" role="status" aria-live="polite">
                <div wire:loading wire:target="refreshImpositionPreview,impositionSettings">Calculating copies…</div>
                <div wire:loading.remove wire:target="refreshImpositionPreview,impositionSettings" class="space-y-1">
                    @if ($this->impositionPreviewError)
                        <p class="text-danger-600">{{ $this->impositionPreviewError }}</p>
                    @elseif ($this->impositionPreview)
                        <p class="font-semibold">Estimated {{ $this->impositionPreview['copies'] }} {{ $this->impositionPreview['copies'] === 1 ? 'copy' : 'copies' }} per sheet</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $this->impositionSettings['width'] }} × {{ $this->impositionSettings['height'] }} mm · {{ number_format($this->impositionPreview['utilisation'], 1) }}% material use</p>
                    @else
                        <p class="text-gray-500">Enter sheet measurements to calculate copies.</p>
                    @endif
                </div>
            </div>
            @if (in_array($imposition?->status, ['queued', 'processing'], true))
                <p class="text-sm text-gray-500" role="status">An imposition is already generating. We will notify you when it is ready.</p>
            @endif
            <div class="flex justify-end">
                <x-filament::button type="submit" wire:loading.attr="disabled" :disabled="! $this->impositionPreview || in_array($imposition?->status, ['queued', 'processing'], true)">
                    Generate files
                </x-filament::button>
            </div>
        </form>
    </x-filament::modal>

    <x-filament::modal id="dieline-template-picker" width="6xl" heading="Choose a dieline" description="Browse the shapes, then select a blueprint to start with its recommended measurements.">
        @php($cards = $this->templateCards())
        <div x-data="{ query: '', terms: @js(array_map(fn ($card) => strtolower($card['name'].' '.$card['description']), $cards)) }" class="space-y-5" x-on:open-modal.window="if ($event.detail.id === 'dieline-template-picker') query = ''">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" x-model="query" placeholder="Search blueprints…" aria-label="Search blueprints" />
            </x-filament::input.wrapper>
            <div class="dieline-template-grid grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($cards as $card)
                    <button type="button" class="dieline-template-card" wire:key="template-card-{{ $card['key'] }}" wire:click="selectTemplate('{{ $card['key'] }}')" wire:loading.attr="disabled" wire:target="selectTemplate" aria-label="{{ $card['name'] }}" aria-describedby="template-description-{{ $card['key'] }}" aria-pressed="{{ data_get($this->data, 'template_key') === $card['key'] ? 'true' : 'false' }}" x-show="{{ Illuminate\Support\Js::from(strtolower($card['name'].' '.$card['description'])) }}.includes(query.toLowerCase().trim())">
                        <span class="dieline-template-thumbnail"><img src="{{ $card['thumbnail'] }}" alt="" loading="lazy" width="240" height="160"></span>
                        <span class="dieline-template-card-name">{{ $card['name'] }}</span>
                        <span class="dieline-template-card-description" id="template-description-{{ $card['key'] }}">{{ $card['description'] }}</span>
                    </button>
                @endforeach
            </div>
            <p x-cloak x-show="query.trim() && ! terms.some(term => term.includes(query.toLowerCase().trim()))" class="text-sm text-gray-500">No matching blueprints. Try another name.</p>
        </div>
    </x-filament::modal>
</x-filament-panels::page>

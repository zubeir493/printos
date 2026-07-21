<x-filament-panels::page full-height>
    <form wire:submit="save" class="dieline-canvas-shell">
        <aside class="dieline-editor-sidebar">
            <div class="dieline-editor-sidebar-scroll">

                <label class="dieline-editor-field">
                    <span>Dieline type <sup>*</sup></span>
                    <x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector('select')?.focus()">
                        <x-filament::input.select wire:model.live="data.template_key">
                            @foreach ($this->templateOptions() as $templateKey => $templateName)
                                <option value="{{ $templateKey }}">{{ $templateName }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('data.template_key')
                        <small>{{ $message }}</small>
                    @enderror
                </label>

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
                                    step="0.1"
                                    min="0"
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

                <button type="button" class="dieline-save-menu" x-on:click="open = ! open" aria-label="Download options">
                    <svg viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M5.2 7.7a1 1 0 0 1 1.4 0L10 11.1l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 9.1a1 1 0 0 1 0-1.4Z" />
                    </svg>
                </button>

                <div class="dieline-download-menu" x-cloak x-show="open" x-transition.origin.bottom.left>
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
</x-filament-panels::page>

@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        class="relative"
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            open: false,
            draft: { start: null, end: null },
            hours: Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0')),
            minutes: Array.from({ length: 12 }, (_, index) => String(index * 5).padStart(2, '0')),
            presets: [
                { label: 'Night', start: '22:00', end: '06:00' },
                { label: 'After hours', start: '17:30', end: '22:00' },
                { label: 'Early morning', start: '05:00', end: '08:00' },
            ],
            init() {
                this.ensureState()
                this.resetDraft()
            },
            ensureState() {
                if (! this.state || typeof this.state !== 'object') {
                    this.state = { start: null, end: null }
                }
            },
            normalize(value) {
                return value ? String(value).slice(0, 5) : null
            },
            display() {
                const start = this.normalize(this.state.start)
                const end = this.normalize(this.state.end)

                return start && end ? `${start} - ${end}` : 'Select time range'
            },
            resetDraft() {
                this.draft = {
                    start: this.normalize(this.state.start) || '22:00',
                    end: this.normalize(this.state.end) || '06:00',
                }
            },
            scrollToSelection() {
                this.$nextTick(() => {
                    for (const which of ['start', 'end']) {
                        for (const part of ['hour', 'minute']) {
                            this.scrollList(which, part)
                        }
                    }
                })
            },
            scrollList(which, part) {
                const value = this.part(which, part)
                const option = this.$root.querySelector(`[data-time-option='${which}-${part}-${value}']`)

                option?.scrollIntoView({ block: 'center' })
            },
            part(which, part) {
                const value = this.normalize(this.draft[which]) || '00:00'
                const pieces = value.split(':')

                return part === 'hour' ? pieces[0] : pieces[1]
            },
            setPart(which, part, value) {
                const hour = part === 'hour' ? value : this.part(which, 'hour')
                const minute = part === 'minute' ? value : this.part(which, 'minute')

                this.draft[which] = `${hour}:${minute}`
                this.scrollList(which, part)
            },
            applyPreset(preset) {
                this.draft = { start: preset.start, end: preset.end }
                this.scrollToSelection()
            },
            apply() {
                this.state = { start: this.draft.start, end: this.draft.end }
                this.open = false
            },
            clear() {
                this.state = { start: null, end: null }
                this.resetDraft()
                this.open = false
            },
            toggle() {
                if (@js($isDisabled)) {
                    return
                }

                this.resetDraft()
                this.open = ! this.open
                this.scrollToSelection()
            },
        }"
        x-on:keydown.escape.window="open = false"
        x-on:click.outside="open = false"
    >
        <x-filament::input.wrapper
            :disabled="$isDisabled"
            x-on:click="toggle()"
            class="cursor-pointer"
        >
            <button
                type="button"
                @disabled($isDisabled)
                class="fi-input flex min-h-10 w-full items-center justify-between gap-3 px-3 py-2 text-left"
            >
                <span
                    class="tabular-nums"
                    x-bind:class="display() === 'Select time range' ? 'text-gray-400 dark:text-gray-500' : ''"
                    x-text="display()"
                ></span>

                <svg
                    class="fi-icon fi-size-md shrink-0 text-gray-400"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-12.25a.75.75 0 0 0-1.5 0V10c0 .199.079.39.22.53l2.5 2.5a.75.75 0 1 0 1.06-1.06l-2.28-2.28V5.75Z" clip-rule="evenodd" />
                </svg>
            </button>
        </x-filament::input.wrapper>

        <div
            x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            class="absolute z-[9999] mt-2 w-[min(28rem,calc(100vw-2rem))] rounded-xl bg-white p-3 shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
        >
            <div class="mb-3 flex flex-wrap gap-2">
                <template x-for="preset in presets" x-bind:key="preset.label">
                    <button
                        type="button"
                        x-on:click="applyPreset(preset)"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/10 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5"
                        x-text="preset.label"
                    ></button>
                </template>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <template x-for="which in ['start', 'end']" x-bind:key="which">
                    <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="which === 'start' ? 'Starts' : 'Ends'"></span>
                            <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white" x-text="draft[which]"></span>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="max-h-44 space-y-1 overflow-y-auto pr-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                <template x-for="hour in hours" x-bind:key="`${which}-hour-${hour}`">
                                    <button
                                        type="button"
                                        x-bind:data-time-option="`${which}-hour-${hour}`"
                                        x-on:click="setPart(which, 'hour', hour)"
                                        class="min-h-9 w-full rounded-md px-2 text-sm tabular-nums transition-colors active:scale-[0.96]"
                                        x-bind:class="part(which, 'hour') === hour ? 'bg-primary-500 text-white shadow-sm' : 'text-gray-700 hover:bg-white dark:text-gray-200 dark:hover:bg-gray-800'"
                                        x-text="hour"
                                    ></button>
                                </template>
                            </div>

                            <div class="max-h-44 space-y-1 overflow-y-auto pl-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                <template x-for="minute in minutes" x-bind:key="`${which}-minute-${minute}`">
                                    <button
                                        type="button"
                                        x-bind:data-time-option="`${which}-minute-${minute}`"
                                        x-on:click="setPart(which, 'minute', minute)"
                                        class="min-h-9 w-full rounded-md px-2 text-sm tabular-nums transition-colors active:scale-[0.96]"
                                        x-bind:class="part(which, 'minute') === minute ? 'bg-primary-500 text-white shadow-sm' : 'text-gray-700 hover:bg-white dark:text-gray-200 dark:hover:bg-gray-800'"
                                        x-text="minute"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-3 flex items-center justify-between border-t border-gray-950/10 pt-3 dark:border-white/10">
                <button
                    type="button"
                    x-on:click="clear()"
                    class="fi-color fi-color-gray fi-link fi-size-sm"
                >
                    Clear
                </button>

                <button
                    type="button"
                    x-on:click="apply()"
                    class="fi-color fi-color-primary bg-primary-500 text-white hover:bg-primary-600 dark:bg-primary-600 dark:hover:bg-primary-700 fi-btn fi-size-sm"
                >
                    Apply
                </button>
            </div>
        </div>
    </div>
</x-dynamic-component>

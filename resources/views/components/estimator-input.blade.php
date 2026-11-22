@props(['path', 'label', 'type' => 'number', 'options' => [], 'suffix' => null, 'min' => 0, 'max' => null, 'step' => 'any', 'disabled' => false])
<label class="estimator-config-field">
    <span>{{ $label }}</span>
    <x-filament::input.wrapper :suffix="$suffix">
        @if ($type === 'select')
            <x-filament::input.select wire:model.live="{{ $path }}" :disabled="$disabled">
                @foreach ($options as $value => $text)
                    <option value="{{ $value }}">{{ $text }}</option>
                @endforeach
            </x-filament::input.select>
        @else
            <x-filament::input :type="$type" wire:model.blur="{{ $path }}" :min="$type === 'number' ? $min : null" :max="$max" :step="$type === 'number' ? $step : null" :disabled="$disabled" />
        @endif
    </x-filament::input.wrapper>
    @error($path)<small class="text-danger-600">{{ $message }}</small>@enderror
</label>

<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="fi-form-actions">
            {{ $this->getFormActions()[0] }}
        </div>
    </form>
</x-filament-panels::page>

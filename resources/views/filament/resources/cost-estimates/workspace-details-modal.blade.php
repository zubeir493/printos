<x-filament::modal id="estimator-details" heading="Production details" width="6xl" sticky-header sticky-footer class="estimator-details-modal">
    {{ $getChildSchema() }}

    <x-slot name="footer">
        <x-filament::button type="button" x-on:click="$dispatch('close-modal', { id: 'estimator-details' })">
            Done
        </x-filament::button>
    </x-slot>
</x-filament::modal>

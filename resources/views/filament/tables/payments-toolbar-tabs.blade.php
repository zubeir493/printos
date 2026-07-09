@php
    use App\Filament\Resources\Payments\Pages\ListPayments;
    use Illuminate\Support\Js;
@endphp

<div
    wire:key="payments-toolbar-tabs"
    x-data="{ activeTab: $wire.$entangle('activeTab', true) }"
>
    <x-filament::tabs label="Payment type">
        @foreach (ListPayments::TABLE_TABS as $tabKey => $tabLabel)
            @php
                $tabKey = (string) $tabKey;
            @endphp

            <x-filament::tabs.item
                :alpine-active="'activeTab === ' . Js::from($tabKey)"
                x-bind:aria-selected="activeTab === {{ Js::from($tabKey) }}"
                x-on:click="activeTab = {{ Js::from($tabKey) }}"
            >
                {{ $tabLabel }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>
</div>

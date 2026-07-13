@php
    use App\Filament\Support\RelationManagerToolbarTabs;
    use Illuminate\Support\Js;

    $activeManager = $activeManager ?? null;
    $tabs = $tabs ?? RelationManagerToolbarTabs::tabsForManager($activeManager);
@endphp

@if (count($tabs) > 1)
    <div
        class="relation-manager-toolbar-tabs"
        wire:key="relation-manager-toolbar-tabs-{{ str($activeManager ?? 'unknown')->afterLast('\\')->kebab() }}"
    >
        <x-filament::tabs label="Related records">
            @foreach ($tabs as $tab)
                <x-filament::tabs.item
                    :active="$tab['manager'] === $activeManager"
                    x-on:click="$wire.$parent.$set('activeRelationManager', {{ Js::from($tab['key']) }})"
                >
                    {{ $tab['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>
@endif

<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
    </form>

    <script>
        (() => {
            if (window.settingsTabsAutoScrollRegistered) {
                return;
            }

            window.settingsTabsAutoScrollRegistered = true;

            const scrollActiveSettingsTabIntoView = () => {
                document
                    .querySelector('.settings-tabs > .fi-tabs .fi-tabs-item.fi-active')
                    ?.scrollIntoView({ block: 'nearest', inline: 'center' });
            };

            document.addEventListener('DOMContentLoaded', scrollActiveSettingsTabIntoView);
            document.addEventListener('livewire:navigated', scrollActiveSettingsTabIntoView);
            document.addEventListener('click', (event) => {
                if (! event.target.closest('.settings-tabs > .fi-tabs .fi-tabs-item')) {
                    return;
                }

                requestAnimationFrame(scrollActiveSettingsTabIntoView);
            });
        })();
    </script>
</x-filament-panels::page>

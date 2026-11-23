@if ($this->record->formula_version !== 'sheetfed-v2')
    <x-filament-panels::header :actions="$this->getCachedHeaderActions()" />
@endif

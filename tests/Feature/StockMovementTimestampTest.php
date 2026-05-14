<?php

use App\Filament\Resources\StockMovements\Schemas\StockMovementForm;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;

it('uses a datetime picker for movement date so time is preserved', function () {
    $schema = StockMovementForm::configure(Schema::make());
    $components = (new ReflectionClass($schema))
        ->getProperty('components')
        ->getValue($schema);

    $movementDate = collect($components)
        ->first(fn ($component) => $component->getName() === 'movement_date');

    expect($movementDate)
        ->toBeInstanceOf(DateTimePicker::class);
});

<?php

namespace App\Filament\Resources\Machines\Schemas;

use App\Models\Machine;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MachineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->maxLength(255),
                Select::make('operation_type')
                    ->options(Machine::OPERATION_TYPES)
                    ->searchable()
                    ->required(),
                TextInput::make('baseline_rounds_per_week')
                    ->label('Baseline Rounds/Week')
                    ->numeric()
                    ->default(0)
                    ->helperText('Expected number of rounds per week for this machine'),
                TextInput::make('hourly_cost')
                    ->numeric()
                    ->default(0)
                    ->suffix('Birr'),
                TextInput::make('production_speed')
                    ->numeric()
                    ->default(0)
                    ->suffix('units/hr'),
            ]);
    }
}

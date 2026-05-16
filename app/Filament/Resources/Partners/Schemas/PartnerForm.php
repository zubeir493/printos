<?php

namespace App\Filament\Resources\Partners\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('address'),
                TextInput::make('tin_number'),
                TextInput::make('email')
                    ->email(),
                Grid::make(2)
                    ->schema([
                        Toggle::make('is_supplier')
                            ->required(),
                        Toggle::make('is_customer')
                            ->required(),
                    ]),
            ]);
    }
}

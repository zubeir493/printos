<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Warehouse;
use App\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get as UtilitiesGet;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->options(UserRole::class)
                    ->required()
                    ->default(UserRole::Design)
                    ->live(),
                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context) => $context === 'create')
                    ->helperText('Leave blank to keep current password')
                    ->label('Password'),
                Select::make('warehouse_ids')
                    ->label('Assigned Warehouses')
                    ->options(fn () => Warehouse::orderBy('name')->pluck('name', 'id')->all())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(fn (UtilitiesGet $get): bool => (($get('role') instanceof UserRole ? $get('role')->value : $get('role')) === UserRole::Warehouse->value))
                    ->helperText('This appears only for warehouse users and supports multiple warehouse assignments.')
                    ->dehydrated(false),
            ]);
    }
}

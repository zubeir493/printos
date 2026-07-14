<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\ExpenseTrackingType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                Select::make('type')
                    ->options([
                        'Asset' => 'Asset',
                        'Liability' => 'Liability',
                        'Equity' => 'Equity',
                        'Revenue' => 'Revenue',
                        'Expense' => 'Expense',
                    ])
                    ->live()
                    ->required(),
                Select::make('default_tracking_type')
                    ->label('Default Expense Tracking')
                    ->options(ExpenseTrackingType::options())
                    ->default(ExpenseTrackingType::NONE->value)
                    ->visible(fn (Get $get): bool => $get('type') === 'Expense')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'Expense'),
            ]);
    }
}

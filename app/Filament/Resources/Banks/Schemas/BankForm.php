<?php

namespace App\Filament\Resources\Banks\Schemas;

use App\Support\Money;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;

class BankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ComponentsSection::make('Bank Information')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Bank Account Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Internal name for this bank account'),
                        TextInput::make('code')
                            ->label('Account Code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique code for this account'),
                        TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Name of the financial institution'),
                        TextInput::make('account_number')
                            ->label('Account Number')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Bank account number'),
                        TextInput::make('account_holder_name')
                            ->label('Account Holder')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Name on the bank account'),
                        TextInput::make('branch')
                            ->label('Branch')
                            ->maxLength(255)
                            ->helperText('Bank branch location'),
                    ])->columnSpan(4),
                ComponentsSection::make()
                    ->columns(1)
                    ->components([
                        Select::make('status')
                            ->label('Account Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'closed' => 'Closed',
                            ])
                            ->default('active')
                            ->required()
                            ->helperText('Current status of this bank account'),
                        TextInput::make('opening_balance')
                            ->label('Opening Balance')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix(fn (): string => Money::suffix())
                            ->visibleOn('create')
                            ->helperText('Balance before transactions recorded in Printerp.'),
                        Textarea::make('notes')
                            ->label('Additional Notes')
                            ->rows(3)
                            ->helperText('Any additional information about this bank account'),
                    ])->columnSpan(2),
            ])->columns(6);
    }
}

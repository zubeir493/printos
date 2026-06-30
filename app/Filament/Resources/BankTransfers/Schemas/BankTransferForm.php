<?php

namespace App\Filament\Resources\BankTransfers\Schemas;

use App\Models\BankTransfer;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BankTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('transfer_number')
                    ->label('Transfer Number')
                    ->default(function (): string {
                        $lastTransfer = BankTransfer::query()->latest('id')->first();
                        $lastNumber = 0;

                        if ($lastTransfer && preg_match('/BT-(\d+)/', $lastTransfer->transfer_number, $matches)) {
                            $lastNumber = (int) $matches[1];
                        }

                        return 'BT-'.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                    })
                    ->readOnly()
                    ->dehydrated(false)
                    ->helperText('Auto-generated transfer reference'),

                Hidden::make('status')
                    ->default('pending'),

                Select::make('from_bank_id')
                    ->label('From Bank')
                    ->relationship('fromBank', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('to_bank_id', null))
                    ->helperText('Select the source bank account'),

                Select::make('to_bank_id')
                    ->label('To Bank')
                    ->relationship('toBank', 'name', function ($query, callable $get) {
                        return $query->where('id', '!=', $get('from_bank_id'));
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (callable $get) => ! $get('from_bank_id'))
                    ->helperText('Select the destination bank account'),

                TextInput::make('amount')
                    ->label('Transfer Amount')
                    ->required()
                    ->numeric()
                    ->suffix(fn (): string => Money::suffix())
                    ->rules(['min:0.01'])
                    ->helperText('Amount to transfer between banks'),

                DatePicker::make('transfer_date')
                    ->label('Transfer Date')
                    ->required()
                    ->default(now())
                    ->helperText('Date when the transfer occurred'),

                TextInput::make('reference')
                    ->label('Reference Number')
                    ->maxLength(255)
                    ->helperText('Optional reference or transaction ID from bank'),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->helperText('Purpose or description of this transfer'),
            ]);
    }
}

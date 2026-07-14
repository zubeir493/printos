<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Partner;
use App\Models\Setting;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('invoice_type')
                                    ->label('Type')
                                    ->options([
                                        'sales' => 'Sales Invoice',
                                        'purchase' => 'Purchase Invoice',
                                        'service' => 'Service Invoice',
                                        'receipt' => 'Payment Receipt',
                                    ])
                                    ->required()
                                    ->native(false),

                                DatePicker::make('invoice_date')
                                    ->label('Invoice Date')
                                    ->required()
                                    ->default(now()),

                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->required()
                                    ->default(fn () => now()->addDays(30))
                                    ->after('invoice_date'),
                                Select::make('partner_id')
                                    ->label('Customer')
                                    ->relationship('partner', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set) => $set('email_recipient', Partner::find($state)?->email)),

                                TextInput::make('email_recipient')
                                    ->label('Email')
                                    ->email()
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->placeholder('customer@example.com'),

                                Toggle::make('send_email')
                                    ->label('Send email immediately')
                                    ->default(false),
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->suffix(fn (): string => Money::suffix())
                                    ->numeric()
                                    ->step(0.01)
                                    ->required()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $vatRate = (float) (Setting::getSettings()->vat_rate ?? 0) / 100;
                                        $tax = $state * $vatRate;
                                        $total = $state + $tax;
                                        $set('tax_amount', $tax);
                                        $set('total_amount', $total);
                                        $set('balance_due', $total);
                                    }),

                                TextInput::make('tax_amount')
                                    ->label(fn () => 'Tax ('.(Setting::getSettings()->vat_rate ?? 0).'%)')
                                    ->suffix(fn (): string => Money::suffix())
                                    ->numeric()
                                    ->step(0.01)
                                    ->required()
                                    ->readOnly()
                                    ->dehydrated(),

                                TextInput::make('total_amount')
                                    ->label('Total')
                                    ->suffix(fn (): string => Money::suffix())
                                    ->numeric()
                                    ->step(0.01)
                                    ->required()
                                    ->readOnly()
                                    ->dehydrated(),
                            ]),
                    ])
                    ->columnSpan(4),
            ])
            ->columns(5);
    }
}

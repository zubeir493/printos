<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Partner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Schemas\Components\Grid as ComponentsGrid;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Core Invoice Information
                ComponentsSection::make('Invoice Details')
                    ->schema([
                        ComponentsGrid::make(2)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->required()
                                    ->readOnly()
                                    ->prefix('#'),

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
                            ]),

                        ComponentsGrid::make(2)
                            ->schema([
                                DatePicker::make('invoice_date')
                                    ->label('Invoice Date')
                                    ->required()
                                    ->default(now()),

                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->required()
                                    ->default(fn () => now()->addDays(30))
                                    ->after('invoice_date'),
                            ]),
                    ]),

                // Customer Information
                ComponentsSection::make('Customer Information')
                    ->schema([
                        ComponentsGrid::make(2)
                            ->schema([
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
                            ]),
                    ]),

                // Financial Information
                ComponentsSection::make('Financial Details')
                    ->schema([
                        ComponentsGrid::make(3)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('ETB')
                                    ->numeric()
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $tax = $state * 0.15; // 15% tax
                                        $total = $state + $tax;
                                        $set('tax_amount', $tax);
                                        $set('total_amount', $total);
                                        $set('balance_due', $total);
                                    }),

                                TextInput::make('tax_amount')
                                    ->label('Tax (15%)')
                                    ->prefix('ETB')
                                    ->numeric()
                                    ->step(0.01)
                                    ->readOnly(),

                                TextInput::make('total_amount')
                                    ->label('Total')
                                    ->prefix('ETB')
                                    ->numeric()
                                    ->step(0.01)
                                    ->readOnly(),
                            ]),
                    ]),

                // Status Management
                ComponentsSection::make('Status Management')
                    ->schema([
                        ComponentsGrid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'sent' => 'Sent',
                                        'paid' => 'Paid',
                                        'overdue' => 'Overdue',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->required()
                                    ->default('draft'),

                                Toggle::make('send_email')
                                    ->label('Send email immediately')
                                    ->default(false)
                                    ->helperText('Send invoice email to customer after creation'),
                            ]),
                    ]),

                // Notes (Optional)
                ComponentsSection::make('Additional Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Internal Notes')
                            ->rows(3)
                            ->placeholder('Add any internal notes or special instructions...')
                            ->helperText('These notes are for internal use only and will not appear on the invoice'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\CashDeposits\Schemas;

use App\Models\Bank;
use App\Models\CashDeposit;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CashDepositForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Deposit details')
                ->description('Record money deposited into a bank account.')
                ->columns(2)
                ->schema([
                    Select::make('deposit_type')
                        ->label('Deposit type')
                        ->options(fn (): array => [
                            CashDeposit::TYPE_CASH_TRANSFER => 'Cash sales balance ('.Money::format(CashDeposit::cashOnHandBalance(), 2).')',
                            CashDeposit::TYPE_OTHER_SOURCES => 'Other sources',
                        ])
                        ->default(CashDeposit::TYPE_CASH_TRANSFER)
                        ->required(),
                    TextInput::make('status_display')
                        ->label('Status')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'view'),
                    TextInput::make('source_account')
                        ->label('Source account')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'view'),
                    DatePicker::make('deposit_date')
                        ->label('Deposit date')
                        ->default(now())
                        ->required(),
                    Select::make('bank_id')
                        ->label('Destination bank')
                        ->options(fn (): array => Bank::query()
                            ->where('status', 'active')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (): ?int => request()->integer('bank_id') ?: null)
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->minValue(0.01)
                        ->suffix(fn (): string => Money::suffix())
                        ->required(),
                    TextInput::make('reference')
                        ->label('Deposit slip reference')
                        ->maxLength(255)
                        ->required(),
                    FileUpload::make('attachment')
                        ->label('Deposit slip')
                        ->disk('local')
                        ->directory('cash-deposits')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                    Hidden::make('status')
                        ->default(CashDeposit::STATUS_PENDING)
                        ->visible(fn (string $operation): bool => $operation !== 'view'),
                ])
                ->columnSpanFull(),
        ]);
    }
}

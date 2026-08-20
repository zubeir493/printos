<?php

namespace App\Filament\Resources\CashDeposits\Schemas;

use App\Models\Account;
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
                ->description('Record cash moving from a cash account into a bank account.')
                ->columns(2)
                ->schema([
                    TextInput::make('deposit_number')
                        ->label('Deposit number')
                        ->default(fn (): string => self::nextDepositNumber())
                        ->readOnly()
                        ->dehydrated(false),
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
                    Select::make('cash_account_id')
                        ->label('Source cash account')
                        ->options(fn (): array => Account::query()
                            ->whereIn('code', [Account::CODE_CASH, Account::CODE_PETTY_CASH])
                            ->orderBy('code')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (): int => Account::getSystemAccount(
                            Account::CODE_CASH,
                            'Cash in Hand',
                            'Asset',
                        )->id)
                        ->required()
                        ->helperText('Available cash is checked when the deposit is posted.'),
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
                        ->default(CashDeposit::STATUS_PENDING),
                ])
                ->columnSpanFull(),
        ]);
    }

    private static function nextDepositNumber(): string
    {
        $lastNumber = CashDeposit::query()->latest('id')->value('deposit_number');
        $sequence = preg_match('/CD-(\d+)/', (string) $lastNumber, $matches) ? (int) $matches[1] + 1 : 1;

        return 'CD-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}

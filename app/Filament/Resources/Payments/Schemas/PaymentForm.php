<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Models\Account;
use App\Models\Bid;
use App\Models\Employee;
use App\Models\ExpenseTrackingItem;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Details')
                    ->description('Choose the preset and enter the business facts. Accounting accounts are handled automatically.')
                    ->schema([
                        Select::make('transaction_type')
                            ->label('Transaction Type')
                            ->options(PaymentTransactionType::paymentFormOptions())
                            ->default(PaymentTransactionType::CUSTOMER_RECEIPT->value)
                            ->searchable()
                            ->helperText(function (Get $get): ?string {
                                return PaymentTransactionType::tryFrom(
                                    $get('transaction_type') ?? PaymentTransactionType::CUSTOMER_RECEIPT->value
                                )?->description();
                            })
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $transactionType = PaymentTransactionType::tryFrom((string) $state);

                                $set('partner_id', null);

                                if ($transactionType === PaymentTransactionType::PETTY_CASH_EXPENSE) {
                                    $set('method', 'cash');
                                    $set('bank_id', null);
                                }

                                if (! $transactionType?->isExpense()) {
                                    self::clearExpenseTrackingFields($set);
                                }
                            })
                            ->required()
                            ->live(),
                        Placeholder::make('payment_number_preview')
                            ->label('Payment #')
                            ->content('Auto-generated on save'),
                        Select::make('partner_id')
                            ->label(function (Get $get): string {
                                return PaymentTransactionType::tryFrom(
                                    $get('transaction_type') ?? PaymentTransactionType::CUSTOMER_RECEIPT->value
                                )?->partnerLabel() ?? 'Counterparty';
                            })
                            ->relationship(
                                'partner',
                                'name',
                                modifyQueryUsing: function ($query, $get) {
                                    return $get('transaction_type') === PaymentTransactionType::SUPPLIER_PAYMENT->value
                                        ? $query->where('is_supplier', true)
                                        : $query->where('is_customer', true);
                                }
                            )
                            ->visible(fn ($get): bool => PaymentTransactionType::tryFrom(
                                $get('transaction_type') ?? PaymentTransactionType::CUSTOMER_RECEIPT->value
                            )?->requiresPartner() ?? false)
                            ->required(fn ($get): bool => PaymentTransactionType::tryFrom(
                                $get('transaction_type') ?? PaymentTransactionType::CUSTOMER_RECEIPT->value
                            )?->requiresPartner() ?? false)
                            ->searchable()
                            ->preload()
                            ->createOptionForm(fn ($get) => $get('transaction_type') === PaymentTransactionType::SUPPLIER_PAYMENT->value
                                ? [
                                    TextInput::make('name')->required(),
                                    TextInput::make('phone')->required(),
                                    TextInput::make('email')->email(),
                                    TextInput::make('address'),
                                    Hidden::make('is_supplier')->default(true),
                                ]
                                : [
                                    TextInput::make('name')->required(),
                                    TextInput::make('phone')->required(),
                                    TextInput::make('email')->email(),
                                    TextInput::make('address'),
                                    Hidden::make('is_customer')->default(true),
                                ]),
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->suffix(fn (): string => Money::suffix()),
                        DatePicker::make('payment_date')
                            ->default(now())
                            ->required(),
                        Select::make('method')
                            ->label('Payment method')
                            ->options(fn (Get $get): array => self::methodOptions($get('transaction_type')))
                            ->default('bank')
                            ->afterStateHydrated(function (Select $component, $record): void {
                                if (! $record) {
                                    return;
                                }

                                $component->state(match ($record->method) {
                                    'bank_transfer' => 'bank',
                                    'check' => 'cheque',
                                    default => $record->method,
                                });
                            })
                            ->required()
                            ->live(),
                        Select::make('bank_id')
                            ->label('Bank Account')
                            ->relationship('bank', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('method') === 'bank' && $get('transaction_type') !== PaymentTransactionType::PETTY_CASH_EXPENSE->value)
                            ->required(fn (Get $get): bool => $get('method') === 'bank' && $get('transaction_type') !== PaymentTransactionType::PETTY_CASH_EXPENSE->value)
                            ->dehydrated(fn (Get $get): bool => $get('method') === 'bank' && $get('transaction_type') !== PaymentTransactionType::PETTY_CASH_EXPENSE->value)
                            ->helperText('Select the bank account for this payment'),
                        Select::make('petty_cash_account_id')
                            ->label('Petty Cash Account')
                            ->options(fn (): array => self::pettyCashAccountOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => self::usesPettyCashAccount($get('transaction_type')))
                            ->required(fn (Get $get): bool => self::usesPettyCashAccount($get('transaction_type')))
                            ->dehydrated(fn (Get $get): bool => self::usesPettyCashAccount($get('transaction_type'))),
                        Select::make('expense_account_id')
                            ->label('Expense Account')
                            ->options(fn (): array => self::expenseAccountOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, int|string|null $state): void {
                                $trackingType = $state
                                    ? Account::query()->whereKey($state)->value('default_tracking_type')
                                    : null;

                                $set('expense_tracking_type', $trackingType ?: ExpenseTrackingType::NONE->value);
                                $set('expense_tracking_item_id', null);
                                $set('expense_tracking_employee_id', null);
                                $set('expense_tracking_bid_id', null);
                            })
                            ->createOptionForm([
                                TextInput::make('code')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Account::class, 'code'),
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('default_tracking_type')
                                    ->label('Default Tracking')
                                    ->options(ExpenseTrackingType::options())
                                    ->default(ExpenseTrackingType::NONE->value)
                                    ->required(),
                                Hidden::make('type')
                                    ->default('Expense'),
                            ])
                            ->createOptionUsing(fn (array $data): int => Account::create([
                                'code' => $data['code'],
                                'name' => $data['name'],
                                'type' => 'Expense',
                                'default_tracking_type' => $data['default_tracking_type'] ?? ExpenseTrackingType::NONE->value,
                            ])->id)
                            ->visible(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type')))
                            ->required(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type')))
                            ->dehydrated(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type'))),
                        Select::make('expense_tracking_type')
                            ->label('Track By')
                            ->options(ExpenseTrackingType::options())
                            ->default(ExpenseTrackingType::NONE->value)
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('expense_tracking_item_id', null);
                                $set('expense_tracking_employee_id', null);
                                $set('expense_tracking_bid_id', null);
                            })
                            ->visible(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type')))
                            ->dehydrated(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type'))),
                        Select::make('expense_tracking_item_id')
                            ->label(fn (Get $get): string => ExpenseTrackingType::tryFrom((string) $get('expense_tracking_type'))?->label() ?? 'Tracking Item')
                            ->options(fn (Get $get): array => self::trackingItemOptions($get('expense_tracking_type')))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => ExpenseTrackingType::tryFrom((string) $get('expense_tracking_type'))?->usesTrackingItem() ?? false)
                            ->required(fn (Get $get): bool => ExpenseTrackingType::tryFrom((string) $get('expense_tracking_type'))?->usesTrackingItem() ?? false)
                            ->dehydrated(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type'))),
                        Select::make('expense_tracking_employee_id')
                            ->label('Employee')
                            ->options(fn (): array => self::employeeOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('expense_tracking_type') === ExpenseTrackingType::EMPLOYEE->value)
                            ->required(fn (Get $get): bool => $get('expense_tracking_type') === ExpenseTrackingType::EMPLOYEE->value)
                            ->dehydrated(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type'))),
                        Select::make('expense_tracking_bid_id')
                            ->label('Bid')
                            ->options(fn (): array => self::bidOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('expense_tracking_type') === ExpenseTrackingType::BID->value)
                            ->required(fn (Get $get): bool => $get('expense_tracking_type') === ExpenseTrackingType::BID->value)
                            ->dehydrated(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type'))),
                        TextInput::make('reference')
                            ->label('Memo / Reference')
                            ->placeholder('For example: receipt number, bill number, or short note')
                            ->helperText('Use this field for later lookup and audit reference.')
                            ->maxLength(255),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function methodOptions(?string $transactionType): array
    {
        if ($transactionType === PaymentTransactionType::PETTY_CASH_EXPENSE->value) {
            return ['cash' => 'Petty Cash'];
        }

        return [
            'cash' => 'Cash',
            'bank' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'cpo' => 'CPO',
        ];
    }

    private static function isExpenseTransaction(?string $transactionType): bool
    {
        return PaymentTransactionType::tryFrom((string) $transactionType)?->isExpense() ?? false;
    }

    private static function usesPettyCashAccount(?string $transactionType): bool
    {
        return PaymentTransactionType::tryFrom((string) $transactionType)?->usesPettyCashAccount() ?? false;
    }

    private static function clearExpenseTrackingFields(Set $set): void
    {
        $set('expense_account_id', null);
        $set('expense_tracking_type', ExpenseTrackingType::NONE->value);
        $set('expense_tracking_item_id', null);
        $set('expense_tracking_employee_id', null);
        $set('expense_tracking_bid_id', null);
        $set('petty_cash_account_id', null);
    }

    /**
     * @return array<int, string>
     */
    private static function expenseAccountOptions(): array
    {
        return Account::query()
            ->where('type', 'Expense')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} - {$account->name}"])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function pettyCashAccountOptions(): array
    {
        return Account::query()
            ->where('type', 'Asset')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} - {$account->name}"])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function trackingItemOptions(?string $trackingType): array
    {
        return ExpenseTrackingItem::query()
            ->active()
            ->where('type', $trackingType)
            ->orderBy('code')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ExpenseTrackingItem $item): array => [$item->id => $item->display_name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function employeeOptions(): array
    {
        return Employee::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->full_name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function bidOptions(): array
    {
        return Bid::query()
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Bid $bid): array => [
                $bid->id => filled($bid->title) ? "{$bid->bid_number} - {$bid->title}" : $bid->bid_number,
            ])
            ->all();
    }
}

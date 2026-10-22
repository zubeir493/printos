<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Models\Account;
use App\Models\Bid;
use App\Models\Employee;
use App\Models\ExpenseTrackingItem;
use App\Support\Money;
use App\Support\PaymentMethod;
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
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $transactionType = PaymentTransactionType::tryFrom((string) $state);

                                $set('partner_id', null);

                                if ($transactionType === PaymentTransactionType::PETTY_CASH_EXPENSE) {
                                    $set('method', 'petty_cash');
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
                            ->hidden()
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
                            ->minValue(0.01)
                            ->live(onBlur: true)
                            ->suffix(fn (): string => Money::suffix()),
                        TextInput::make('withholding_amount')
                            ->label('Withholding')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(fn (Get $get): float => (float) ($get('amount') ?? 0))
                            ->suffix(fn (): string => Money::suffix())
                            ->visible(fn (Get $get): bool => self::supportsWithholding($get('transaction_type')))
                            ->dehydrated(fn (Get $get): bool => self::supportsWithholding($get('transaction_type'))),
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
                            ->visible(fn (Get $get): bool => $get('transaction_type') !== PaymentTransactionType::PETTY_CASH_EXPENSE->value)
                            ->required(fn (Get $get): bool => $get('transaction_type') !== PaymentTransactionType::PETTY_CASH_EXPENSE->value)
                            ->dehydratedWhenHidden()
                            ->live(),
                        Select::make('bank_id')
                            ->label('Bank Account')
                            ->relationship('bank', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => self::requiresBankAccount($get('method')) && $get('transaction_type') !== PaymentTransactionType::PETTY_CASH_EXPENSE->value)
                            ->required(fn (Get $get): bool => self::requiresBankAccount($get('method')) && $get('transaction_type') !== PaymentTransactionType::PETTY_CASH_EXPENSE->value)
                            ->dehydrated(fn (Get $get): bool => self::requiresBankAccount($get('method')) && $get('transaction_type') !== PaymentTransactionType::PETTY_CASH_EXPENSE->value),
                        Select::make('petty_cash_account_id')
                            ->label('Petty Cash Account')
                            ->visible(false)
                            ->required(false)
                            ->dehydrated(false),
                        Select::make('expense_account_id')
                            ->label('Expense Category')
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
                        Hidden::make('expense_tracking_type')
                            ->default(ExpenseTrackingType::NONE->value)
                            ->dehydrated(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type'))),
                        Select::make('expense_tracking_item_id')
                            ->label(fn (Get $get): string => ExpenseTrackingType::tryFrom((string) $get('expense_tracking_type'))?->label() ?? 'Tracking Item')
                            ->searchable()
                            ->getSearchResultsUsing(fn (Get $get, string $search): array => self::trackingItemOptions($get('expense_tracking_type'), $search))
                            ->getOptionLabelUsing(fn (Get $get, $value): ?string => ExpenseTrackingItem::query()->active()->where('type', $get('expense_tracking_type'))->find($value)?->display_name)
                            ->visible(fn (Get $get): bool => ExpenseTrackingType::tryFrom((string) $get('expense_tracking_type'))?->usesTrackingItem() ?? false)
                            ->required(fn (Get $get): bool => ExpenseTrackingType::tryFrom((string) $get('expense_tracking_type'))?->usesTrackingItem() ?? false)
                            ->dehydrated(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type'))),
                        Select::make('expense_tracking_employee_id')
                            ->label('Employee')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::employeeOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => Employee::find($value)?->full_name)
                            ->visible(fn (Get $get): bool => $get('expense_tracking_type') === ExpenseTrackingType::EMPLOYEE->value)
                            ->required(fn (Get $get): bool => $get('expense_tracking_type') === ExpenseTrackingType::EMPLOYEE->value)
                            ->dehydrated(fn (Get $get): bool => self::isExpenseTransaction($get('transaction_type'))),
                        Select::make('expense_tracking_bid_id')
                            ->label('Bid')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::bidOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ($bid = Bid::find($value)) ? (filled($bid->title) ? "{$bid->bid_number} - {$bid->title}" : $bid->bid_number) : null)
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
            return ['petty_cash' => 'Petty Cash'];
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

    private static function supportsWithholding(?string $transactionType): bool
    {
        return in_array($transactionType, [
            PaymentTransactionType::CUSTOMER_RECEIPT->value,
            PaymentTransactionType::SUPPLIER_PAYMENT->value,
        ], true);
    }

    private static function requiresBankAccount(?string $method): bool
    {
        return PaymentMethod::requiresBank($method);
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
    private static function trackingItemOptions(?string $trackingType, string $search): array
    {
        return ExpenseTrackingItem::query()
            ->active()
            ->where('type', $trackingType)
            ->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
            ->limit(50)
            ->orderBy('code')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ExpenseTrackingItem $item): array => [$item->id => $item->display_name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function employeeOptions(string $search): array
    {
        return Employee::query()
            ->where(fn ($query) => $query->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
            ->limit(50)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->full_name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function bidOptions(string $search): array
    {
        return Bid::query()
            ->where(fn ($query) => $query->where('bid_number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"))
            ->limit(50)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Bid $bid): array => [
                $bid->id => filled($bid->title) ? "{$bid->bid_number} - {$bid->title}" : $bid->bid_number,
            ])
            ->all();
    }
}

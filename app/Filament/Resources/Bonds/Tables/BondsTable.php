<?php

namespace App\Filament\Resources\Bonds\Tables;

use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Bank;
use App\Models\Bond;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BondsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bid.bid_number')
                    ->label('Bid')
                    ->description(fn(Bond $record): ?string => $record->issuingPartner?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn($state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => Bond::typeOptions()[$state] ?? str($state)->headline()->toString())
                    ->color(fn(string $state): string => $state === Bond::TYPE_PERFORMANCE ? 'warning' : 'info'),
                TextColumn::make('bank.name')
                    ->label('Bank')
                    ->state(fn(Bond $record): ?string => $record->cpo_bank_name ?: $record->bank?->name)
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => Bond::statusOptions()[$state] ?? str($state)->headline()->toString())
                    ->color(fn(string $state): string => match ($state) {
                        Bond::STATUS_PENDING => 'gray',
                        Bond::STATUS_ACTIVE => 'warning',
                        Bond::STATUS_RECOVERED => 'success',
                        Bond::STATUS_FORFEITED, Bond::STATUS_EXPIRED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('issue_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('issue_date_range', 'issue_date', 'Issue date'),

                SelectFilter::make('type')
                    ->options(Bond::typeOptions()),
                SelectFilter::make('status')
                    ->options(Bond::statusOptions()),
                SelectFilter::make('issuing_partner_id')
                    ->label('Procuring Entity')
                    ->relationship('issuingPartner', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->relationship('bank', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('return_bond')
                    ->label('Return Bond')
                    ->icon('heroicon-o-banknotes')
                    ->color('gray')
                    ->visible(fn(Bond $record): bool => filled($record->issue_payment_id) && blank($record->recovery_payment_id))
                    ->schema(fn(Bond $record): array => self::bondPaymentSchema($record))
                    ->action(fn(Bond $record, array $data): mixed => self::handleBondAction(
                        fn() => $record->recover(...self::bondRecoveryData($record, $data)),
                        'Bond returned',
                    )),
            ])
            ->defaultSort('issue_date', 'desc');
    }

    private static function bondPaymentSchema(Bond $bond): array
    {
        if ($bond->issuePayment?->method === 'cpo') {
            return [
                Grid::make(2)->schema([
                    Hidden::make('method')->default('cpo'),
                    Hidden::make('cpo_bank_name')->default($bond->cpo_bank_name),
                    DatePicker::make('payment_date')
                        ->default(now())
                        ->required(),
                    TextInput::make('reference')
                        ->label('Memo / Reference')
                        ->maxLength(255),
                ]),
            ];
        }

        return [
            Grid::make(2)->schema([
                Select::make('method')
                    ->label('Payment method')
                    ->options([
                        'cash' => 'Cash',
                        'bank' => 'Bank Transfer',
                        'cheque' => 'Cheque',
                        'cpo' => 'CPO',
                    ])
                    ->default('bank')
                    ->required()
                    ->live(),
                Select::make('bank_id')
                    ->label('Bank Account')
                    ->options(fn(): array => Bank::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->visible(fn(callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                    ->required(fn(callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                    ->dehydrated(fn(callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
                TextInput::make('cpo_bank_name')
                    ->label('CPO Bank')
                    ->maxLength(255)
                    ->visible(fn(callable $get): bool => $get('method') === 'cpo')
                    ->required(fn(callable $get): bool => $get('method') === 'cpo')
                    ->dehydrated(fn(callable $get): bool => $get('method') === 'cpo'),
                DatePicker::make('payment_date')
                    ->default(now())
                    ->required(),
                TextInput::make('reference')
                    ->label('Memo / Reference')
                    ->maxLength(255),
            ]),
        ];
    }

    private static function bondRecoveryData(Bond $bond, array $data): array
    {
        return [
            'paymentDate' => $data['payment_date'],
            'method' => $bond->issuePayment?->method === 'cpo' ? 'cpo' : $data['method'],
            'bankId' => $data['bank_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'cpoBankName' => $data['cpo_bank_name'] ?? $bond->cpo_bank_name,
        ];
    }

    private static function handleBondAction(callable $callback, string $message): null
    {
        try {
            $callback();

            Notification::make()->title($message)->success()->send();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.amount' => $exception->getMessage(),
            ]);
        }

        return null;
    }
}

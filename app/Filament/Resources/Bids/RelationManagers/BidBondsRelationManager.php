<?php

namespace App\Filament\Resources\Bids\RelationManagers;

use App\Models\Bank;
use App\Models\BidBond;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BidBondsRelationManager extends RelationManager
{
    protected static string $relationship = 'bidBonds';

    protected static ?string $title = 'Bid Bonds';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('issuing_partner_id')
                ->label('Issuing Institution')
                ->relationship('issuingPartner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_supplier', true))
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')->required(),
                    TextInput::make('phone'),
                    TextInput::make('email')->email(),
                    TextInput::make('tin_number'),
                    Hidden::make('is_supplier')->default(true),
                ])
                ->required(),
            TextInput::make('amount')
                ->numeric()
                ->required()
                ->suffix('Birr'),
            DatePicker::make('expiry_date'),
            TextInput::make('reference')
                ->maxLength(255),
            Textarea::make('notes')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('issuingPartner.name')
                    ->label('Issuer')
                    ->searchable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BidBond::statusOptions()[$state] ?? str($state)->headline()->toString())
                    ->color(fn (string $state): string => match ($state) {
                        BidBond::STATUS_PENDING => 'gray',
                        BidBond::STATUS_ACTIVE => 'info',
                        BidBond::STATUS_RECOVERED => 'success',
                        BidBond::STATUS_FORFEITED, BidBond::STATUS_EXPIRED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('issue_date')->date(),
                TextColumn::make('recovery_date')->date(),
                TextColumn::make('expiry_date')->date(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'status' => BidBond::STATUS_PENDING,
                    ]),
            ])
            ->recordActions([
                Action::make('issue')
                    ->label('Issue Bond')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn (BidBond $record): bool => blank($record->issue_payment_id))
                    ->schema($this->paymentSchema())
                    ->action(fn (BidBond $record, array $data) => $this->handleBondAction(
                        fn () => $record->issue(
                            paymentDate: $data['payment_date'],
                            method: $data['method'],
                            bankId: $data['bank_id'] ?? null,
                            reference: $data['reference'] ?? null,
                        ),
                        'Bid bond issued',
                    )),
                Action::make('recover')
                    ->label('Recover Bond')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (BidBond $record): bool => filled($record->issue_payment_id) && blank($record->recovery_payment_id) && $record->status !== BidBond::STATUS_FORFEITED)
                    ->schema($this->paymentSchema())
                    ->action(fn (BidBond $record, array $data) => $this->handleBondAction(
                        fn () => $record->recover(
                            paymentDate: $data['payment_date'],
                            method: $data['method'],
                            bankId: $data['bank_id'] ?? null,
                            reference: $data['reference'] ?? null,
                        ),
                        'Bid bond recovered',
                    )),
                Action::make('forfeit')
                    ->label('Mark Forfeited')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (BidBond $record): bool => filled($record->issue_payment_id) && blank($record->recovery_payment_id) && $record->status !== BidBond::STATUS_FORFEITED)
                    ->action(fn (BidBond $record) => $this->handleBondAction(
                        fn () => $record->markForfeited(),
                        'Bid bond marked forfeited',
                    )),
            ])
            ->defaultSort('expiry_date');
    }

    private function paymentSchema(): array
    {
        return [
            Select::make('method')
                ->label('Paid Via')
                ->options([
                    'cash' => 'Cash',
                    'bank' => 'Bank Transfer',
                    'cheque' => 'Cheque',
                ])
                ->default('bank')
                ->required()
                ->live(),
            Select::make('bank_id')
                ->label('Bank Account')
                ->options(fn (): array => Bank::query()->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->visible(fn (callable $get): bool => $get('method') === 'bank')
                ->required(fn (callable $get): bool => $get('method') === 'bank'),
            DatePicker::make('payment_date')
                ->default(now())
                ->required(),
            TextInput::make('reference')
                ->label('Memo / Reference')
                ->maxLength(255),
        ];
    }

    private function handleBondAction(callable $callback, string $message): void
    {
        try {
            $callback();

            Notification::make()
                ->title($message)
                ->success()
                ->send();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.bank_id' => $exception->getMessage(),
            ]);
        }
    }
}

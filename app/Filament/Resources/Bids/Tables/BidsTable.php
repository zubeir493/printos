<?php

namespace App\Filament\Resources\Bids\Tables;

use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\Bid;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BidsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bid_number')
                    ->label('Bid #')
                    ->description(fn (Bid $record): ?string => $record->partner?->name)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(45),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Bid::statusOptions()[$state] ?? str($state)->headline()->toString())
                    ->color(fn (string $state): string => match ($state) {
                        Bid::STATUS_DRAFT => 'gray',
                        Bid::STATUS_SUBMITTED => 'info',
                        Bid::STATUS_AWARDED => 'success',
                        Bid::STATUS_BOND_SENT => 'warning',
                        Bid::STATUS_BOND_RECOVERED => 'success',
                        Bid::STATUS_LOST => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('estimated_value')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('bid_bond_amount')
                    ->label('Bond')
                    ->formatStateUsing(fn ($state): string => Money::format($state ?? 0)),
                TextColumn::make('deadline_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Bid::statusOptions()),
                SelectFilter::make('partner_id')
                    ->label('Procuring Entity')
                    ->relationship('partner', 'name'),
            ])
            ->defaultSort('deadline_date')
            ->recordActions([
                ActionGroup::make([
                    Action::make('submit')
                        ->label('Submit Bid')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->visible(fn (Bid $record): bool => $record->status === Bid::STATUS_DRAFT && PanelAccess::canManageJobOrders())
                        ->requiresConfirmation()
                        ->action(function (Bid $record): void {
                            $record->markSubmitted();

                            Notification::make()->title('Bid submitted')->success()->send();
                        }),
                    Action::make('send_bond')
                        ->label('Send Bid Bond')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('warning')
                        ->visible(fn (Bid $record): bool => $record->status === Bid::STATUS_DRAFT
                            && (float) ($record->bid_bond_amount ?? 0) > 0
                            && blank($record->currentBidBond()?->issue_payment_id)
                            && PanelAccess::canAccessFinanceSection())
                        ->schema(self::bidBondPaymentSchema())
                        ->action(fn (Bid $record, array $data): mixed => self::handleBidBondAction(
                            fn () => DB::transaction(function () use ($record, $data): void {
                                $record->prepareBidBond()->issue(
                                    paymentDate: $data['payment_date'],
                                    method: $data['method'],
                                    bankId: $data['bank_id'] ?? null,
                                    reference: $data['reference'] ?? null,
                                    cpoBankName: $data['cpo_bank_name'] ?? null,
                                );

                                $record->markSubmitted();
                            }),
                            'Bid bond sent',
                        )),
                    Action::make('return_bond')
                        ->label('Return Bid Bond')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn (Bid $record): bool => filled($record->currentBidBond()?->issue_payment_id)
                            && blank($record->currentBidBond()?->recovery_payment_id)
                            && PanelAccess::canAccessFinanceSection())
                        ->schema(self::bidBondPaymentSchema())
                        ->action(fn (Bid $record, array $data): mixed => self::handleBidBondAction(
                            fn () => DB::transaction(function () use ($record, $data): void {
                                $record->currentBidBond()?->recover(
                                    paymentDate: $data['payment_date'],
                                    method: $data['method'],
                                    bankId: $data['bank_id'] ?? null,
                                    reference: $data['reference'] ?? null,
                                    cpoBankName: $data['cpo_bank_name'] ?? null,
                                );

                                if ($record->status === Bid::STATUS_SUBMITTED) {
                                    $record->markLost();
                                }
                            }),
                            'Bid bond returned',
                        )),
                    Action::make('send_performance_bond')
                        ->label('Send Performance Bond')
                        ->icon('heroicon-o-shield-check')
                        ->color('warning')
                        ->visible(fn (Bid $record): bool => $record->status === Bid::STATUS_AWARDED
                            && blank($record->activePerformanceBond())
                            && PanelAccess::canAccessFinanceSection())
                        ->schema(self::performanceBondPaymentSchema())
                        ->action(fn (Bid $record, array $data): mixed => self::handleBidBondAction(
                            fn () => DB::transaction(function () use ($record, $data): void {
                                $record->preparePerformanceBond(
                                    amount: (float) $data['amount'],
                                    notes: $data['notes'] ?? null,
                                )->issue(
                                    paymentDate: $data['payment_date'],
                                    method: $data['method'],
                                    bankId: $data['bank_id'] ?? null,
                                    cpoBankName: $data['cpo_bank_name'] ?? null,
                                );

                                $record->markBondSent();
                            }),
                            'Performance bond sent',
                        )),
                    Action::make('return_performance_bond')
                        ->label('Recover Performance Bond')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn (Bid $record): bool => in_array($record->status, [Bid::STATUS_AWARDED, Bid::STATUS_BOND_SENT], true)
                            && filled($record->activePerformanceBond())
                            && PanelAccess::canAccessFinanceSection())
                        ->schema(self::bidBondPaymentSchema())
                        ->action(fn (Bid $record, array $data): mixed => self::handleBidBondAction(
                            fn () => DB::transaction(function () use ($record, $data): void {
                                $record->activePerformanceBond()?->recover(
                                    paymentDate: $data['payment_date'],
                                    method: $data['method'],
                                    bankId: $data['bank_id'] ?? null,
                                    reference: $data['reference'] ?? null,
                                    cpoBankName: $data['cpo_bank_name'] ?? null,
                                );

                                $record->markBondRecovered();
                            }),
                            'Performance bond recovered',
                        )),
                    Action::make('award')
                        ->label('Mark Awarded')
                        ->icon('heroicon-o-trophy')
                        ->color('success')
                        ->visible(fn (Bid $record): bool => $record->status === Bid::STATUS_SUBMITTED && PanelAccess::canManageJobOrders())
                        ->requiresConfirmation()
                        ->action(function (Bid $record): void {
                            $record->markAwarded();

                            Notification::make()->title('Bid awarded')->success()->send();
                        }),
                    Action::make('mark_lost')
                        ->label('Mark Lost')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Bid $record): bool => $record->status === Bid::STATUS_SUBMITTED && PanelAccess::canManageJobOrders())
                        ->requiresConfirmation()
                        ->action(function (Bid $record): void {
                            $record->markLost();

                            Notification::make()->title('Bid marked lost')->danger()->send();
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function bidBondPaymentSchema(): array
    {
        return [
            Grid::make(2)->schema(self::bondPaymentFields()),
        ];
    }

    private static function performanceBondPaymentSchema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('amount')
                    ->label('Performance Bond Amount')
                    ->numeric()
                    ->required()
                    ->suffix('Birr'),
                ...self::bondPaymentFields(includeReference: false),
                Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]),
        ];
    }

    private static function bondPaymentFields(bool $includeReference = true): array
    {
        $fields = [
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
                ->options(fn (): array => Bank::query()->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->visible(fn (callable $get): bool => $get('method') === 'bank')
                ->required(fn (callable $get): bool => $get('method') === 'bank')
                ->dehydrated(fn (callable $get): bool => $get('method') === 'bank'),
            TextInput::make('cpo_bank_name')
                ->label('CPO Bank')
                ->maxLength(255)
                ->visible(fn (callable $get): bool => $get('method') === 'cpo')
                ->required(fn (callable $get): bool => $get('method') === 'cpo')
                ->dehydrated(fn (callable $get): bool => $get('method') === 'cpo'),
            DatePicker::make('payment_date')
                ->default(now())
                ->required(),
        ];

        if ($includeReference) {
            $fields[] = TextInput::make('reference')
                ->label('Memo / Reference')
                ->maxLength(255);
        }

        return $fields;
    }

    private static function handleBidBondAction(callable $callback, string $message): null
    {
        try {
            $callback();

            Notification::make()->title($message)->success()->send();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.bid_bond_amount' => $exception->getMessage(),
            ]);
        }

        return null;
    }
}

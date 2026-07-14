<?php

namespace App\Filament\Resources\Bids\Pages\Concerns;

use App\Filament\Resources\Bids\BidResource;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\Bid;
use App\Models\Bond;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

trait InteractsWithBidActions
{
    protected function bidHeaderActions(bool $includeEdit = false, bool $includeDelete = false): array
    {
        $draftActions = [
            $this->sendBidBondAction(),
            $this->submitBidAction(),
        ];

        if ($includeEdit) {
            array_unshift($draftActions, EditAction::make()
                ->color('gray')
                ->visible(fn (): bool => BidResource::canEdit($this->record)));
        }

        if ($includeDelete) {
            $draftActions[] = DeleteAction::make()
                ->visible(fn (): bool => BidResource::canDelete($this->record));
        }

        return [
            ActionGroup::make($draftActions)
                ->visible(fn (): bool => $this->record->status === Bid::STATUS_DRAFT),
            ActionGroup::make([
                $this->returnBidBondAction(),
                $this->awardBidAction(),
                $this->loseBidAction(),
            ])
                ->visible(fn (): bool => $this->record->status === Bid::STATUS_SUBMITTED),
            $this->sendPerformanceBondAction(),
            $this->returnPerformanceBondAction(),
        ];
    }

    protected function submitBidAction(): Action
    {
        return Action::make('submit')
            ->label('Submit Bid')
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->visible(fn (): bool => $this->record->status === Bid::STATUS_DRAFT && PanelAccess::canManageJobOrders())
            ->requiresConfirmation()
            ->action(function (): void {
                $this->record->markSubmitted();
                $this->refreshBidForm();

                Notification::make()->title('Bid submitted')->success()->send();
            });
    }

    protected function awardBidAction(): Action
    {
        return Action::make('award')
            ->label('Mark Awarded')
            ->icon('heroicon-o-trophy')
            ->color('gray')
            ->visible(fn (): bool => $this->record->status === Bid::STATUS_SUBMITTED && PanelAccess::canManageJobOrders())
            ->requiresConfirmation()
            ->action(function (): void {
                $this->record->markAwarded();
                $this->refreshBidForm();

                Notification::make()->title('Bid awarded')->success()->send();
            });
    }

    protected function loseBidAction(): Action
    {
        return Action::make('mark_lost')
            ->label('Mark Lost')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (): bool => $this->record->status === Bid::STATUS_SUBMITTED && PanelAccess::canManageJobOrders())
            ->requiresConfirmation()
            ->action(function (): void {
                $this->record->markLost();
                $this->refreshBidForm();

                Notification::make()->title('Bid marked lost')->danger()->send();
            });
    }

    protected function sendBidBondAction(): Action
    {
        return Action::make('send_bond')
            ->label('Send Bid Bond')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn (): bool => $this->record->status === Bid::STATUS_DRAFT
                && (float) ($this->record->bid_bond_amount ?? 0) > 0
                && blank($this->record->currentBidBond()?->issue_payment_id)
                && PanelAccess::canManageBidBonds())
            ->schema($this->bidBondPaymentSchema())
            ->action(fn (array $data): mixed => $this->handleBidBondAction(
                fn () => DB::transaction(function () use ($data): void {
                    $this->record->prepareBidBond()->issue(
                        paymentDate: $data['payment_date'],
                        method: $data['method'],
                        bankId: $data['bank_id'] ?? null,
                        reference: $data['reference'] ?? null,
                        cpoBankName: $data['cpo_bank_name'] ?? null,
                    );

                    $this->record->markSubmitted();
                }),
                'Bid bond sent',
            ));
    }

    protected function returnBidBondAction(): Action
    {
        return Action::make('return_bond')
            ->label('Return Bid Bond')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => filled($this->record->currentBidBond()?->issue_payment_id)
                && blank($this->record->currentBidBond()?->recovery_payment_id)
                && PanelAccess::canManageBidBonds())
            ->schema(fn (): array => $this->bidBondPaymentSchema($this->record->currentBidBond()))
            ->action(fn (array $data): mixed => $this->handleBidBondAction(
                fn () => DB::transaction(function () use ($data): void {
                    $bond = $this->record->currentBidBond();

                    $bond?->recover(...$this->bondRecoveryData($bond, $data));

                    if ($this->record->status === Bid::STATUS_SUBMITTED) {
                        $this->record->markLost();
                    }
                }),
                'Bid bond returned',
            ));
    }

    protected function sendPerformanceBondAction(): Action
    {
        return Action::make('send_performance_bond')
            ->label('Send Performance Bond')
            ->icon('heroicon-o-shield-check')
            ->color('gray')
            ->visible(fn (): bool => $this->record->status === Bid::STATUS_AWARDED
                && blank($this->record->activePerformanceBond())
                && PanelAccess::canManageBidBonds())
            ->schema($this->performanceBondPaymentSchema())
            ->action(fn (array $data): mixed => $this->handleBidBondAction(
                fn () => DB::transaction(function () use ($data): void {
                    $this->record->preparePerformanceBond(
                        amount: (float) $data['amount'],
                        notes: $data['notes'] ?? null,
                    )->issue(
                        paymentDate: $data['payment_date'],
                        method: $data['method'],
                        bankId: $data['bank_id'] ?? null,
                        cpoBankName: $data['cpo_bank_name'] ?? null,
                    );

                    $this->record->markBondSent();
                }),
                'Performance bond sent',
            ));
    }

    protected function returnPerformanceBondAction(): Action
    {
        return Action::make('return_performance_bond')
            ->label('Recover Performance Bond')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => in_array($this->record->status, [Bid::STATUS_AWARDED, Bid::STATUS_BOND_SENT], true)
                && filled($this->record->activePerformanceBond())
                && PanelAccess::canManageBidBonds())
            ->schema(fn (): array => $this->bidBondPaymentSchema($this->record->activePerformanceBond()))
            ->action(fn (array $data): mixed => $this->handleBidBondAction(
                fn () => DB::transaction(function () use ($data): void {
                    $bond = $this->record->activePerformanceBond();

                    $bond?->recover(...$this->bondRecoveryData($bond, $data));

                    $this->record->markBondRecovered();
                }),
                'Performance bond recovered',
            ));
    }

    protected function bidBondPaymentSchema(?Bond $bond = null): array
    {
        return [
            Grid::make(2)->schema($this->bondPaymentFields(bond: $bond)),
        ];
    }

    protected function performanceBondPaymentSchema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('amount')
                    ->label('Performance Bond Amount')
                    ->numeric()
                    ->required()
                    ->suffix(fn (): string => Money::suffix()),
                ...$this->bondPaymentFields(includeReference: false),
                Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]),
        ];
    }

    protected function bondPaymentFields(bool $includeReference = true, ?Bond $bond = null): array
    {
        if ($bond?->issuePayment?->method === 'cpo') {
            return $this->cpoRecoveryPaymentFields($includeReference, $bond);
        }

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

    protected function cpoRecoveryPaymentFields(bool $includeReference, Bond $bond): array
    {
        $fields = [
            Hidden::make('method')->default('cpo'),
            Hidden::make('cpo_bank_name')->default($bond->cpo_bank_name),
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

    protected function bondRecoveryData(?Bond $bond, array $data): array
    {
        return [
            'paymentDate' => $data['payment_date'],
            'method' => $bond?->issuePayment?->method === 'cpo' ? 'cpo' : $data['method'],
            'bankId' => $data['bank_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'cpoBankName' => $data['cpo_bank_name'] ?? $bond?->cpo_bank_name,
        ];
    }

    protected function handleBidBondAction(callable $callback, string $message): null
    {
        try {
            $callback();
            $this->refreshBidForm();

            Notification::make()->title($message)->success()->send();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.bid_bond_amount' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    protected function refreshBidForm(): void
    {
        $this->record->refresh();
        $this->refreshFormData([
            'status',
            'bid_bond_amount',
        ]);
    }
}

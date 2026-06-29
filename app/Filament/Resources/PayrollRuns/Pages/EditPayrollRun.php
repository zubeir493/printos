<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\Bank;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\ExportPayrollRegisterCsv;
use App\Services\Hr\GeneratePayrollPayments;
use App\Services\Hr\PostPayrollRun;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use RuntimeException;

class EditPayrollRun extends EditRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('calculate')
                    ->label(fn (): string => $this->record->employees()->exists() ? 'Recalculate' : 'Calculate')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->record->status === 'draft')
                    ->action(function (): void {
                        app(CalculatePayrollRun::class)->handle($this->record);
                        $this->record->refresh();
                        $this->fillForm();
                        $count = $this->record->employees()->count();
                        $netPay = $this->record->employees()->sum('net_pay');

                        Notification::make()
                            ->title("Payroll calculated for {$count} employees")
                            ->body('Net pay total: '.number_format((float) $netPay, 2).' Birr')
                            ->success()
                            ->send();
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->visible(fn (): bool => $this->record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $this->ensureCalculatedPayrollExists();

                        app(PostPayrollRun::class)->handle($this->record);
                        $this->record->refresh();

                        Notification::make()->title('Payroll approved and journal posted')->success()->send();
                    }),
                Action::make('generatePayments')
                    ->label('Generate Payments')
                    ->visible(fn (): bool => $this->record->status === 'approved')
                    ->schema([
                        Select::make('bank_id')
                            ->label('Pay From Bank')
                            ->options(fn (): array => $this->bankOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Callout::make('Insufficient bank balance')
                            ->description(fn (Get $get): string => $this->insufficientBankBalanceMessage((int) $get('bank_id')))
                            ->danger()
                            ->visible(fn (Get $get): bool => $this->selectedBankCannotCoverPayroll((int) $get('bank_id'))),
                    ])
                    ->requiresConfirmation()
                    ->action(function (array $data): void {
                        app(GeneratePayrollPayments::class)->handle($this->record, 'bank', (int) $data['bank_id']);
                        $this->record->refresh();
                        Notification::make()->title('Payroll payments generated')->success()->send();
                    }),
                Action::make('exportRegister')
                    ->label('Export CSV')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->employees()->exists())
                    ->action(fn () => app(ExportPayrollRegisterCsv::class)->download($this->record)),
            ]),
        ];
    }

    private function ensureCalculatedPayrollExists(): void
    {
        if (! $this->record->employees()->exists() || (float) $this->record->employees()->sum('net_pay') <= 0) {
            throw new RuntimeException('Calculate payroll and review employee salary rows before approval.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function bankOptions(): array
    {
        return Bank::query()
            ->where('status', 'active')
            ->orderBy('bank_name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Bank $bank): array => [
                $bank->id => $bank->name.' (available: '.Money::abbreviate($bank->current_balance, 2).')',
            ])
            ->all();
    }

    private function selectedBankCannotCoverPayroll(int $bankId): bool
    {
        if (! $bankId) {
            return false;
        }

        $bankBalance = (float) Bank::query()->whereKey($bankId)->value('current_balance');

        return $bankBalance < $this->remainingPayrollPaymentTotal();
    }

    private function insufficientBankBalanceMessage(int $bankId): string
    {
        $bankBalance = (float) Bank::query()->whereKey($bankId)->value('current_balance');
        $required = $this->remainingPayrollPaymentTotal();

        return 'Available balance is '.Money::abbreviate($bankBalance, 2).', but this payroll needs '.Money::abbreviate($required, 2).'. Select another bank or fund this account first.';
    }

    private function remainingPayrollPaymentTotal(): float
    {
        return (float) $this->record
            ->employees()
            ->whereNull('payment_id')
            ->sum('net_pay');
    }
}

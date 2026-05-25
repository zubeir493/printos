<?php

namespace App\Observers;

use App\Enums\PaymentTransactionType;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use App\Services\Accounting\CreatePaymentJournalEntry;
use App\Support\Money;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class PaymentObserver
{
    public function creating(Payment $payment): void
    {
        $transactionType = $payment->transaction_type
            ?? $this->legacyTransactionType($payment->payment_type, $payment->direction);

        $payment->transaction_type = $transactionType;
        $payment->direction = PaymentTransactionType::tryFrom($transactionType)?->direction()
            ?? PaymentTransactionType::from($this->legacyTransactionType($payment->payment_type, $payment->direction))->direction();

        $resolvedType = PaymentTransactionType::tryFrom($payment->transaction_type);

        if ($resolvedType && ! $resolvedType->requiresPartner() && ! $payment->partner_id) {
            $payment->partner_id = $this->resolveInternalPartnerId();
        }

        if (! $payment->payment_type) {
            $payment->payment_type = 'standard';
        }

        if ($this->isBankPayment($payment) && $payment->direction === 'outbound') {
            $bank = Bank::query()->find($payment->bank_id);

            if (! $bank || (float) $bank->current_balance < (float) $payment->amount) {
                throw new \RuntimeException(sprintf(
                    'Insufficient balance in %s. Available: %s.',
                    $bank?->name ?? 'the selected bank',
                    Money::format($bank?->current_balance ?? 0)
                ));
            }
        }
    }

    public function created(Payment $payment): void
    {
        app(CreatePaymentJournalEntry::class)->handle($payment);

        if ($payment->direction === 'inbound') {
            $recipients = User::whereIn('role', [UserRole::Admin->value, UserRole::Finance->value])->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PaymentReceivedNotification($payment));
            }
        }

        if (! $this->isBankPayment($payment)) {
            return;
        }

        if ($payment->direction === 'outbound') {
            $payment->bank()->decrement('current_balance', $payment->amount);

            return;
        }

        $payment->bank()->increment('current_balance', $payment->amount);
    }

    public function updating(Payment $payment): void
    {
        $hasJournalEntries = JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->exists();

        if ($hasJournalEntries) {
            throw new \RuntimeException('Cannot edit payment after it has been posted to the accounting ledger.');
        }
    }

    protected function legacyTransactionType(?string $paymentType, ?string $direction): string
    {
        return match ($paymentType) {
            'expense' => PaymentTransactionType::DIRECT_EXPENSE->value,
            'petty_cash' => $direction === 'inbound'
                ? PaymentTransactionType::PETTY_CASH_FUNDING->value
                : PaymentTransactionType::PETTY_CASH_EXPENSE->value,
            default => $direction === 'outbound'
                ? PaymentTransactionType::SUPPLIER_PAYMENT->value
                : PaymentTransactionType::CUSTOMER_RECEIPT->value,
        };
    }

    private function isBankPayment(Payment $payment): bool
    {
        return $payment->bank_id && in_array($payment->method, ['bank', 'bank_transfer'], true);
    }

    protected function resolveInternalPartnerId(): int
    {
        return Partner::firstOrCreate([
            'name' => 'Internal Payment',
        ], [
            'is_customer' => false,
            'is_supplier' => false,
        ])->id;
    }
}

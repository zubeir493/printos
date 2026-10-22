<?php

namespace App\Observers;

use App\Enums\PaymentTransactionType;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\Payment;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PaymentVoidedNotification;
use App\Services\Accounting\CreatePaymentJournalEntry;
use App\Support\Money;
use App\Support\NotificationRecipients;
use App\Support\PaymentMethod;
use App\UserRole;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class PaymentObserver
{
    public function creating(Payment $payment): void
    {
        if ($payment->method === 'check') {
            $payment->method = 'cheque';
        }
        if (! is_finite((float) $payment->amount) || round((float) $payment->amount, 2) <= 0) {
            throw ValidationException::withMessages(['data.amount' => 'Payment amount must be greater than zero.']);
        }
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

        $payment->withholding_amount ??= 0;

        if ((float) $payment->withholding_amount < 0) {
            throw new \RuntimeException('Withholding cannot be negative.');
        }

        if ((float) $payment->withholding_amount > (float) $payment->amount) {
            throw new \RuntimeException('Withholding cannot be greater than the settled payment amount.');
        }

        if (PaymentMethod::requiresBank($payment->method)) {
            $bank = Bank::query()->lockForUpdate()->find($payment->bank_id);

            if (! $bank) {
                throw new \RuntimeException('A valid bank account is required for this payment method.');
            }

            if ($payment->direction === 'outbound' && (float) $bank->current_balance < $this->cashAmount($payment)) {
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
            $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Finance, UserRole::Sales, UserRole::Operations);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PaymentReceivedNotification($payment));
            }
        }

        if (! $this->isBankPayment($payment)) {
            return;
        }

        if ($payment->direction === 'outbound') {
            $payment->bank()->decrement('current_balance', $this->cashAmount($payment));

            return;
        }

        $payment->bank()->increment('current_balance', $this->cashAmount($payment));
    }

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('voided_at') || ! $payment->voided_at) {
            return;
        }

        $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Finance, UserRole::Sales, UserRole::Operations);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new PaymentVoidedNotification($payment->refresh()));
        }
    }

    public function updating(Payment $payment): void
    {
        $hasJournalEntries = JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->exists();

        if ($hasJournalEntries && ! $this->isOnlyVoidMetadataBeingUpdated($payment)) {
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
        return $payment->bank_id && PaymentMethod::requiresBank($payment->method);
    }

    private function cashAmount(Payment $payment): float
    {
        return max(0, round((float) $payment->amount - (float) $payment->withholding_amount, 2));
    }

    private function isOnlyVoidMetadataBeingUpdated(Payment $payment): bool
    {
        $updatedColumns = array_keys($payment->getDirty());

        return $updatedColumns !== []
            && collect($updatedColumns)->every(fn (string $column): bool => in_array($column, [
                'voided_at',
                'voided_by',
                'void_reason',
                'updated_at',
            ], true));
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

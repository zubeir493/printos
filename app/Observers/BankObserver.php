<?php

namespace App\Observers;

use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class BankObserver
{
    /**
     * Handle the Bank "created" event.
     */
    public function created(Bank $bank): void
    {
        Log::info('Bank created', ['bank_id' => $bank->id, 'code' => $bank->code]);

        // Initialize balance to 0 for new banks
        if ($bank->current_balance === null) {
            $bank->update(['current_balance' => 0]);
        }
    }

    /**
     * Handle the Bank "updated" event.
     */
    public function updated(Bank $bank): void
    {
        Log::info('Bank updated', ['bank_id' => $bank->id, 'changes' => $bank->getDirty()]);

        // If status changed to inactive/closed, log warning
        if ($bank->wasChanged('status') && in_array($bank->status, ['inactive', 'closed'])) {
            Log::warning('Bank deactivated', [
                'bank_id' => $bank->id,
                'code' => $bank->code,
                'status' => $bank->status,
            ]);
        }
    }

    /**
     * Handle the Bank "deleted" event.
     */
    public function deleted(Bank $bank): void
    {
        Log::warning('Bank deleted', ['bank_id' => $bank->id, 'code' => $bank->code]);
    }

    /**
     * Handle related payment created/updated/deleted events
     * This will be called from PaymentObserver
     */
    public function handlePaymentChange(Bank $bank, Payment $payment, string $action): void
    {
        Log::info('Bank balance changed due to payment', [
            'bank_id' => $bank->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'direction' => $payment->direction,
            'action' => $action,
        ]);

        // Update bank balance
        $bank->updateBalance();
    }

    /**
     * Handle related bank transfer events
     * This will be called from BankTransferObserver
     */
    public function handleTransferChange(Bank $bank, BankTransfer $transfer, string $action): void
    {
        Log::info('Bank balance changed due to transfer', [
            'bank_id' => $bank->id,
            'transfer_id' => $transfer->id,
            'amount' => $transfer->amount,
            'status' => $transfer->status,
            'action' => $action,
        ]);

        // Only update balance for completed transfers
        if ($transfer->status === 'completed') {
            $bank->updateBalance();
        }
    }
}

<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankService
{
    /**
     * Create a new bank transfer with proper validation
     */
    public function createTransfer(array $data, ?User $user = null): BankTransfer
    {
        return DB::transaction(function () use ($data, $user) {
            $fromBank = Bank::findOrFail($data['from_bank_id']);
            $toBank = Bank::findOrFail($data['to_bank_id']);

            // Validate transfer
            $this->validateTransfer($fromBank, $toBank, $data['amount']);

            $transfer = BankTransfer::create([
                'from_bank_id' => $data['from_bank_id'],
                'to_bank_id' => $data['to_bank_id'],
                'amount' => $data['amount'],
                'transfer_date' => $data['transfer_date'] ?? now(),
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'pending',
                'created_by' => $user?->id,
            ]);

            Log::info('Bank transfer created', [
                'transfer_id' => $transfer->id,
                'from_bank' => $fromBank->code,
                'to_bank' => $toBank->code,
                'amount' => $data['amount'],
                'created_by' => $user?->id,
            ]);

            return $transfer;
        });
    }

    /**
     * Complete a bank transfer with balance updates
     */
    public function completeTransfer(BankTransfer $transfer, ?User $user = null): void
    {
        $transfer->complete($user);

        Log::info('Bank transfer completed', [
            'transfer_id' => $transfer->id,
            'from_bank' => $transfer->fromBank?->code,
            'to_bank' => $transfer->toBank?->code,
            'amount' => $transfer->amount,
            'completed_by' => $user?->id,
        ]);
    }

    /**
     * Cancel a bank transfer
     */
    public function cancelTransfer(BankTransfer $transfer, ?User $user = null): void
    {
        if ($transfer->status === 'completed') {
            throw new \Exception('Cannot cancel a completed transfer');
        }

        $transfer->update([
            'status' => 'cancelled',
            'completed_by' => $user?->id,
            'completed_at' => now(),
        ]);

        Log::info('Bank transfer cancelled', [
            'transfer_id' => $transfer->id,
            'cancelled_by' => $user?->id,
        ]);
    }

    /**
     * Process a payment and update bank balance
     */
    public function processPayment(Payment $payment): void
    {
        if (! $payment->bank_id || $payment->method !== 'bank') {
            return;
        }

        Log::info('Payment processed for bank', [
            'payment_id' => $payment->id,
            'bank_id' => $payment->bank_id,
            'amount' => $payment->amount,
            'direction' => $payment->direction,
        ]);
    }

    /**
     * Get bank balance summary
     */
    public function getBalanceSummary(): array
    {
        $banks = Bank::with(['payments', 'transfersFrom', 'transfersTo'])->get();

        return $banks->map(function ($bank) {
            return [
                'id' => $bank->id,
                'code' => $bank->code,
                'name' => $bank->name,
                'bank_name' => $bank->bank_name,
                'current_balance' => $bank->current_balance,
                'calculated_balance' => $bank->calculated_balance,
                'total_inflow' => $bank->total_inflow,
                'total_outflow' => $bank->total_outflow,
                'status' => $bank->status,
                'payments_count' => $bank->payments()->count(),
                'transfers_count' => $bank->transfersFrom()->count() + $bank->transfersTo()->count(),
            ];
        })->toArray();
    }

    /**
     * Validate transfer rules
     */
    private function validateTransfer(Bank $fromBank, Bank $toBank, float $amount): void
    {
        if ($fromBank->id === $toBank->id) {
            throw new \Exception('Cannot transfer to the same bank');
        }

        if ($amount <= 0) {
            throw new \Exception('Transfer amount must be positive');
        }

        if ($fromBank->status !== 'active') {
            throw new \Exception("Source bank {$fromBank->name} is not active");
        }

        if ($toBank->status !== 'active') {
            throw new \Exception("Destination bank {$toBank->name} is not active");
        }
    }

    /**
     * Recalculate all bank balances
     */
    public function recalculateAllBalances(): void
    {
        $banks = Bank::all();

        foreach ($banks as $bank) {
            $bank->updateBalance();
        }

        Log::info('All bank balances recalculated', ['banks_count' => $banks->count()]);
    }
}

<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Bank;
use App\Models\CashDeposit;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReverseCashDeposit
{
    public function handle(CashDeposit $deposit, string $reason, ?User $user = null): CashDeposit
    {
        return DB::transaction(function () use ($deposit, $reason, $user): CashDeposit {
            $deposit = CashDeposit::query()->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== CashDeposit::STATUS_POSTED) {
                throw new RuntimeException('Only posted cash deposits can be reversed.');
            }

            $bank = Bank::query()->lockForUpdate()->findOrFail($deposit->bank_id);
            $sourceAccount = in_array($deposit->deposit_type, [
                CashDeposit::TYPE_OTHER_INCOME,
                CashDeposit::TYPE_OTHER_SOURCES,
            ], true)
                ? Account::query()->lockForUpdate()->findOrFail($deposit->income_account_id)
                : Account::query()->lockForUpdate()->findOrFail($deposit->cash_account_id);

            if ((float) $bank->current_balance < (float) $deposit->amount) {
                throw new RuntimeException('The bank balance is too low to reverse this deposit.');
            }

            $originalJournal = $deposit->journalEntries()
                ->whereNull('reversal_of_journal_entry_id')
                ->where('status', 'posted')
                ->firstOrFail();
            $bankAccount = Account::getSystemAccount(Account::CODE_BANK, 'Bank Current Account', 'Asset');
            $timestamp = now();
            $reversal = JournalEntry::create([
                'date' => $timestamp->toDateString(),
                'reference' => 'REV-'.$deposit->deposit_number,
                'source_type' => CashDeposit::class,
                'source_id' => $deposit->id,
                'narration' => 'Reversal of '.$deposit->deposit_number.': '.$reason,
                'total_debit' => $deposit->amount,
                'total_credit' => $deposit->amount,
                'status' => 'posted',
                'posted_at' => $timestamp,
                'reversal_of_journal_entry_id' => $originalJournal->id,
            ]);

            JournalItem::create([
                'journal_entry_id' => $reversal->id,
                'account_id' => $sourceAccount->id,
                'debit' => $deposit->amount,
                'credit' => 0,
            ]);
            JournalItem::create([
                'journal_entry_id' => $reversal->id,
                'account_id' => $bankAccount->id,
                'debit' => 0,
                'credit' => $deposit->amount,
            ]);

            $bank->decrement('current_balance', $deposit->amount);
            $deposit->update([
                'status' => CashDeposit::STATUS_REVERSED,
                'reversed_by' => $user?->id,
                'reversed_at' => $timestamp,
                'reversal_reason' => $reason,
            ]);

            return $deposit->refresh();
        }, attempts: 3);
    }
}

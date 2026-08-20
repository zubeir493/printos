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

class PostCashDeposit
{
    public function handle(CashDeposit $deposit, ?User $user = null): CashDeposit
    {
        return DB::transaction(function () use ($deposit, $user): CashDeposit {
            $deposit = CashDeposit::query()->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== CashDeposit::STATUS_PENDING) {
                throw new RuntimeException('Only pending cash deposits can be posted.');
            }

            $bank = Bank::query()->lockForUpdate()->findOrFail($deposit->bank_id);
            $cashAccount = Account::query()->lockForUpdate()->findOrFail($deposit->cash_account_id);

            if ($bank->status !== 'active') {
                throw new RuntimeException('Cash can only be deposited into an active bank account.');
            }

            if (! in_array($cashAccount->code, [Account::CODE_CASH, Account::CODE_PETTY_CASH], true)) {
                throw new RuntimeException('Select a valid cash account.');
            }

            $availableCash = (float) JournalItem::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
                ->where('journal_items.account_id', $cashAccount->id)
                ->where('journal_entries.status', 'posted')
                ->selectRaw('COALESCE(SUM(journal_items.debit - journal_items.credit), 0) AS balance')
                ->value('balance');

            if ($availableCash < (float) $deposit->amount) {
                throw new RuntimeException(sprintf(
                    'Insufficient cash available. Available: %.2f.',
                    $availableCash,
                ));
            }

            $bankAccount = Account::getSystemAccount(Account::CODE_BANK, 'Bank Current Account', 'Asset');
            $timestamp = now();
            $journal = JournalEntry::create([
                'date' => $deposit->deposit_date,
                'reference' => $deposit->deposit_number,
                'source_type' => CashDeposit::class,
                'source_id' => $deposit->id,
                'attachment' => $deposit->attachment,
                'narration' => $deposit->notes ?: 'Cash deposited to '.$bank->name,
                'total_debit' => $deposit->amount,
                'total_credit' => $deposit->amount,
                'status' => 'posted',
                'posted_at' => $timestamp,
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $bankAccount->id,
                'debit' => $deposit->amount,
                'credit' => 0,
            ]);
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $cashAccount->id,
                'debit' => 0,
                'credit' => $deposit->amount,
            ]);

            $bank->increment('current_balance', $deposit->amount);
            $deposit->update([
                'status' => CashDeposit::STATUS_POSTED,
                'posted_by' => $user?->id,
                'posted_at' => $timestamp,
            ]);

            return $deposit->refresh();
        }, attempts: 3);
    }
}

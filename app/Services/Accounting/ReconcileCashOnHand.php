<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\CashDeposit;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use App\Support\SequentialNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReconcileCashOnHand
{
    public function handle(float $amount, int $offsetAccountId, string $reason, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($amount, $offsetAccountId, $reason): JournalEntry {
            $cashAccount = Account::query()
                ->lockForUpdate()
                ->whereKey(CashDeposit::cashOnHandAccount()->id)
                ->firstOrFail();
            $offsetAccount = Account::query()->lockForUpdate()->findOrFail($offsetAccountId);
            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw new RuntimeException('The reconciliation amount must be greater than zero.');
            }

            if ($offsetAccount->is($cashAccount)) {
                throw new RuntimeException('Cash on Hand cannot be used as the offset account.');
            }

            if (! in_array($offsetAccount->type, ['Equity', 'Liability', 'Revenue'], true)) {
                throw new RuntimeException('Choose an equity, liability, or revenue account as the offset.');
            }

            $cashBalance = $this->balanceFor($cashAccount);
            $deficit = round(max(0, -$cashBalance), 2);

            if ($deficit <= 0) {
                throw new RuntimeException('Cash on Hand is not currently negative.');
            }

            if ($amount > $deficit) {
                throw new RuntimeException(sprintf(
                    'The reconciliation amount cannot exceed the current deficit of %.2f.',
                    $deficit,
                ));
            }

            $timestamp = now();
            $journal = JournalEntry::create([
                'date' => $timestamp->toDateString(),
                'reference' => SequentialNumber::next(
                    lockName: 'cash_reconciliations',
                    modelClass: JournalEntry::class,
                    column: 'reference',
                    prefix: 'CR-',
                    padding: 6,
                    likePattern: 'CR-%',
                ),
                'source_type' => 'cash_reconciliation',
                'narration' => 'Cash on Hand reconciliation: '.$reason,
                'total_debit' => $amount,
                'total_credit' => $amount,
                'status' => 'posted',
                'posted_at' => $timestamp,
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $cashAccount->id,
                'debit' => $amount,
                'credit' => 0,
            ]);
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $offsetAccount->id,
                'debit' => 0,
                'credit' => $amount,
            ]);

            return $journal->load('journalItems');
        }, attempts: 3);
    }

    private function balanceFor(Account $account): float
    {
        return round((float) JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_items.account_id', $account->id)
            ->where('journal_entries.status', 'posted')
            ->selectRaw('COALESCE(SUM(journal_items.debit - journal_items.credit), 0) AS balance')
            ->value('balance'), 2);
    }
}

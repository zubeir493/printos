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
            $depositType = $deposit->deposit_type ?: CashDeposit::TYPE_CASH_TRANSFER;
            $sourceAccount = $this->resolveSourceAccount($deposit, $depositType);

            if ($bank->status !== 'active') {
                throw new RuntimeException('Deposits can only be made into an active bank account.');
            }

            if ($depositType === CashDeposit::TYPE_CASH_TRANSFER) {
                $availableCash = (float) JournalItem::query()
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
                    ->where('journal_items.account_id', $sourceAccount->id)
                    ->where('journal_entries.status', 'posted')
                    ->selectRaw('COALESCE(SUM(journal_items.debit - journal_items.credit), 0) AS balance')
                    ->value('balance');

                if ($availableCash < (float) $deposit->amount) {
                    throw new RuntimeException(sprintf(
                        'Insufficient cash available. Available: %.2f.',
                        $availableCash,
                    ));
                }
            }

            $bankAccount = Account::getSystemAccount(Account::CODE_BANK, 'Bank Current Account', 'Asset');
            $timestamp = now();
            $journal = JournalEntry::create([
                'date' => $deposit->deposit_date,
                'reference' => $deposit->deposit_number,
                'source_type' => CashDeposit::class,
                'source_id' => $deposit->id,
                'attachment' => $deposit->attachment,
                'narration' => $deposit->notes ?: (in_array($depositType, [
                    CashDeposit::TYPE_OTHER_INCOME,
                    CashDeposit::TYPE_OTHER_SOURCES,
                ], true)
                    ? 'Other income deposited to '.$bank->name
                    : 'Cash deposited to '.$bank->name),
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
                'account_id' => $sourceAccount->id,
                'debit' => 0,
                'credit' => $deposit->amount,
            ]);

            $bank->increment('current_balance', $deposit->amount);
            $deposit->update([
                'status' => CashDeposit::STATUS_POSTED,
                'posted_by' => $user?->id,
                'posted_at' => $timestamp,
                'cash_account_id' => $depositType === CashDeposit::TYPE_CASH_TRANSFER ? $sourceAccount->id : null,
                'income_account_id' => in_array($depositType, [
                    CashDeposit::TYPE_OTHER_INCOME,
                    CashDeposit::TYPE_OTHER_SOURCES,
                ], true) ? $sourceAccount->id : null,
            ]);

            return $deposit->refresh();
        }, attempts: 3);
    }

    private function resolveSourceAccount(CashDeposit $deposit, string $depositType): Account
    {
        if (in_array($depositType, [
            CashDeposit::TYPE_OTHER_INCOME,
            CashDeposit::TYPE_OTHER_SOURCES,
        ], true)) {
            $incomeAccount = $deposit->income_account_id
                ? Account::query()->lockForUpdate()->findOrFail($deposit->income_account_id)
                : Account::query()->lockForUpdate()->findOrFail(CashDeposit::otherIncomeAccount()->id);

            if ($incomeAccount->type !== 'Revenue' || $incomeAccount->code === Account::CODE_SALES_REVENUE) {
                throw new RuntimeException('Select a valid non-sales income account.');
            }

            return $incomeAccount;
        }

        if ($depositType !== CashDeposit::TYPE_CASH_TRANSFER) {
            throw new RuntimeException('Select a valid deposit type.');
        }

        return Account::query()
            ->lockForUpdate()
            ->whereKey(CashDeposit::cashOnHandAccount()->id)
            ->firstOrFail();
    }
}

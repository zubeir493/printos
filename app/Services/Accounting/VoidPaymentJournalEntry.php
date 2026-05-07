<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoidPaymentJournalEntry
{
    public function handle(Payment $payment, string $reason, ?User $user = null): JournalEntry
    {
        if ($payment->voided_at) {
            throw new RuntimeException('This payment has already been voided.');
        }

        $originalJournal = $payment->journalEntries()
            ->whereNull('reversal_of_journal_entry_id')
            ->where('status', 'posted')
            ->orderBy('id')
            ->first();

        if (! $originalJournal) {
            throw new RuntimeException('No posted journal entry was found for this payment.');
        }

        return DB::transaction(function () use ($payment, $reason, $user, $originalJournal) {
            $timestamp = now();

            $originalJournal->update([
                'status' => 'void',
                'voided_at' => $timestamp,
            ]);

            $reversalJournal = JournalEntry::create([
                'date' => $timestamp->toDateString(),
                'reference' => 'REV-'.$originalJournal->reference,
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'narration' => 'Reversal of '.$originalJournal->reference.($reason ? ' - '.$reason : ''),
                'total_debit' => $originalJournal->total_debit,
                'total_credit' => $originalJournal->total_credit,
                'status' => 'posted',
                'posted_at' => $timestamp,
                'reversal_of_journal_entry_id' => $originalJournal->id,
            ]);

            foreach ($originalJournal->journalItems as $item) {
                JournalItem::create([
                    'journal_entry_id' => $reversalJournal->id,
                    'account_id' => $item->account_id,
                    'debit' => $item->credit,
                    'credit' => $item->debit,
                ]);
            }

            Payment::whereKey($payment->id)->update([
                'voided_at' => $timestamp,
                'voided_by' => $user?->id,
                'void_reason' => $reason,
            ]);

            return $reversalJournal;
        });
    }
}

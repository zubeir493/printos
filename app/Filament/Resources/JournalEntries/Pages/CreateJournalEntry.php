<?php

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $items = $this->form->getRawState()['JournalItems'] ?? [];
        $totals = $this->validatedTotals($items);

        $data['total_debit'] = $totals['debit'];
        $data['total_credit'] = $totals['credit'];
        $data['status'] = 'posted';
        $data['posted_at'] = now();

        return $data;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{debit: float, credit: float}
     */
    private function validatedTotals(array $items): array
    {
        if (count($items) < 2) {
            throw ValidationException::withMessages([
                'JournalItems' => 'A journal entry must have at least two lines.',
            ]);
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $debit = round((float) ($item['debit'] ?? 0), 2);
            $credit = round((float) ($item['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0) {
                throw ValidationException::withMessages([
                    'JournalItems' => 'Journal debit and credit amounts cannot be negative.',
                ]);
            }

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    'JournalItems' => 'Each journal line must be either a debit or a credit, not both.',
                ]);
            }

            if ($debit === 0.0 && $credit === 0.0) {
                throw ValidationException::withMessages([
                    'JournalItems' => 'Each journal line must have a debit or credit amount.',
                ]);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        if ($totalDebit <= 0 || abs($totalDebit - $totalCredit) > 0.00001) {
            throw ValidationException::withMessages([
                'JournalItems' => 'Journal entries must have equal total debits and credits.',
            ]);
        }

        return [
            'debit' => $totalDebit,
            'credit' => $totalCredit,
        ];
    }
}

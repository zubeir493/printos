<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialReportService
{
    public function trialBalance(?string $startDate, ?string $endDate): array
    {
        $rows = $this->accountRows($startDate, $endDate)->map(function (array $row) {
            $row['balance'] = $row['debit'] - $row['credit'];

            return $row;
        });

        return [
            'rows' => $rows,
            'totals' => [
                'debit' => $rows->sum('debit'),
                'credit' => $rows->sum('credit'),
                'balance' => $rows->sum('balance'),
            ],
        ];
    }

    public function incomeStatement(?string $startDate, ?string $endDate): array
    {
        $rows = $this->accountRows($startDate, $endDate)
            ->filter(fn (array $row) => in_array($row['type'], ['Revenue', 'Expense'], true))
            ->map(function (array $row) {
                if ($row['type'] === 'Revenue') {
                    $row['display_amount'] = $row['credit'] - $row['debit'];
                } else {
                    $row['display_amount'] = $row['debit'] - $row['credit'];
                }

                return $row;
            })
            ->values();

        $totalRevenue = $rows
            ->where('type', 'Revenue')
            ->sum('display_amount');

        $totalExpense = $rows
            ->where('type', 'Expense')
            ->sum('display_amount');

        return [
            'rows' => $rows,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income' => $totalRevenue - $totalExpense,
        ];
    }

    public function profitLoss(?string $startDate, ?string $endDate): array
    {
        $rows = $this->accountRows($startDate, $endDate)
            ->filter(fn (array $row) => in_array($row['type'], ['Revenue', 'Expense'], true))
            ->map(function (array $row) {
                $row['display_amount'] = ($row['type'] === 'Revenue')
                    ? $row['credit'] - $row['debit']
                    : $row['debit'] - $row['credit'];

                return $row;
            })
            ->values();

        $totalRevenue = $rows->where('type', 'Revenue')->sum('display_amount');
        $totalExpense = $rows->where('type', 'Expense')->sum('display_amount');

        return [
            'rows' => $rows,
            'revenue_rows' => $rows->where('type', 'Revenue'),
            'expense_rows' => $rows->where('type', 'Expense'),
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income' => $totalRevenue - $totalExpense,
        ];
    }

    public function generalLedgerQuery(?string $startDate, ?string $endDate, ?int $accountId = null): Builder
    {
        $query = JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
            ->where('journal_entries.status', 'posted')
            ->when($accountId, fn ($query) => $query->where('journal_items.account_id', $accountId))
            ->when($endDate, fn ($query) => $query->whereDate('journal_entries.date', '<=', Carbon::parse($endDate)->toDateString()))
            ->select('journal_items.*')
            ->selectRaw('journal_entries.date as entry_date')
            ->selectRaw('journal_entries.reference as entry_reference')
            ->selectRaw('journal_entries.narration as entry_narration')
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('accounts.name as account_name')
            ->selectRaw('accounts.type as account_type')
            ->selectRaw(
                'SUM(journal_items.debit - journal_items.credit) OVER (PARTITION BY journal_items.account_id ORDER BY journal_entries.date, journal_entries.id, journal_items.id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) as running_balance',
            )
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_items.id');

        return JournalItem::query()->fromSub($query->toBase(), 'journal_items')
            ->select('journal_items.*')
            ->when($startDate, fn ($query) => $query->whereDate('entry_date', '>=', Carbon::parse($startDate)->toDateString()))
            ->orderBy('entry_date')->orderBy('id');
    }

    public function generalLedger(?string $startDate, ?string $endDate, ?int $accountId = null): array
    {
        $rows = $this->generalLedgerQuery($startDate, $endDate, $accountId)->get();

        return [
            'rows' => $rows,
            'total_debit' => $rows->sum('debit'),
            'total_credit' => $rows->sum('credit'),
            'net_movement' => $rows->sum(fn ($row) => $row->debit - $row->credit),
            'record_count' => $rows->count(),
        ];
    }

    public function balanceSheet(?string $asOfDate): array
    {
        $rows = $this->accountRows(null, $asOfDate)
            ->filter(fn (array $row) => in_array($row['type'], ['Asset', 'Liability', 'Equity'], true))
            ->map(function (array $row) {
                $row['display_amount'] = match ($row['type']) {
                    'Asset' => $row['debit'] - $row['credit'],
                    default => $row['credit'] - $row['debit'],
                };

                return $row;
            })
            ->values();

        $incomeStatement = $this->incomeStatement(null, $asOfDate);
        $currentEarnings = $incomeStatement['net_income'];

        return [
            'rows' => $rows,
            'assets' => $rows->where('type', 'Asset'),
            'liabilities' => $rows->where('type', 'Liability'),
            'equity' => $rows->where('type', 'Equity'),
            'current_earnings' => $currentEarnings,
            'total_assets' => $rows->where('type', 'Asset')->sum('display_amount'),
            'total_liabilities' => $rows->where('type', 'Liability')->sum('display_amount'),
            'total_equity' => $rows->where('type', 'Equity')->sum('display_amount') + $currentEarnings,
        ];
    }

    private function accountRows(?string $startDate, ?string $endDate): Collection
    {
        $balances = JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->when($startDate, fn ($query) => $query->whereDate('journal_entries.date', '>=', Carbon::parse($startDate)->toDateString()))
            ->when($endDate, fn ($query) => $query->whereDate('journal_entries.date', '<=', Carbon::parse($endDate)->toDateString()))
            ->select('journal_items.account_id')
            ->selectRaw('SUM(journal_items.debit) as debit_total')
            ->selectRaw('SUM(journal_items.credit) as credit_total')
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id');

        return Account::query()
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($balances) {
                $balance = $balances->get($account->id);

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debit' => (float) ($balance->debit_total ?? 0),
                    'credit' => (float) ($balance->credit_total ?? 0),
                ];
            });
    }
}

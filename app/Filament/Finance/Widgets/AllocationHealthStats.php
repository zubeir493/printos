<?php

namespace App\Filament\Finance\Widgets;

use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AllocationHealthStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Unallocated Funds
        // Difference between total payments and total allocations
        $totalPayments = Payment::sum('amount');
        $totalAllocations = PaymentAllocation::sum('allocated_amount');
        $unallocatedFunds = max(0, $totalPayments - $totalAllocations);

        // 2. Pending Reconciliations (Draft Journal Entries)
        $pendingJournals = JournalEntry::where('status', 'draft')->count();

        return [
            Stat::make('Unallocated Funds', Money::abbreviate($unallocatedFunds, precision: 2))
                ->description('Payments not yet linked to orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($unallocatedFunds > 1000 ? 'danger' : 'success'),

            Stat::make('Journal Drafts', $pendingJournals)
                ->description('Awaiting posting/review')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($pendingJournals > 5 ? 'warning' : 'success'),

            Stat::make('Total Payments Recv.', Money::abbreviate($totalPayments, precision: 2))
                ->description('Life-time aggregate')
                ->color('primary'),
        ];
    }
}

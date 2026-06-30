<?php

namespace App\Filament\Widgets;

use App\Services\Accounting\FinancialReportService;
use App\Support\FiscalCalendar;
use Livewire\Component;

class TrialBalanceDifferenceWidget extends Component
{
    public function render()
    {
        $report = app(FinancialReportService::class)->trialBalance(
            FiscalCalendar::currentFiscalYearStart()->toDateString(),
            now()->toDateString()
        );

        $difference = $report['totals']['balance'];
        $isBalanced = abs($difference) < 0.01;

        return view('filament.widgets.trial-balance-difference', compact('difference', 'isBalanced'));
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class OverdueInvoiceReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:overdue-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate overdue invoice report';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating Overdue Invoice Report');
        $this->info('================================');

        $overdueInvoices = Invoice::overdue()->with('partner')->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('No overdue invoices found!');

            return Command::SUCCESS;
        }

        $totalOverdue = $overdueInvoices->sum('balance_due');
        $this->info("Total Overdue Invoices: {$overdueInvoices->count()}");
        $this->info("Total Overdue Amount: {$totalOverdue} Birr");
        $this->newLine();

        // Group by partner
        $byPartner = $overdueInvoices->groupBy('partner_id');

        foreach ($byPartner as $partnerId => $invoices) {
            $partner = $invoices->first()->partner;
            $partnerTotal = $invoices->sum('balance_due');

            $this->info("Partner: {$partner->name}");
            $this->info("  Invoices: {$invoices->count()}");
            $this->info("  Total Due: {$partnerTotal} Birr");

            foreach ($invoices as $invoice) {
                $daysOverdue = $invoice->due_date->diffInDays(now());
                $this->line("    - {$invoice->invoice_number} ({$daysOverdue} days overdue) - {$invoice->balance_due} Birr");
            }
            $this->newLine();
        }

        // Aging report
        $this->info('Aging Report:');
        $this->info('============');

        $agingRanges = [
            '1-30 days' => [1, 30],
            '31-60 days' => [31, 60],
            '61-90 days' => [61, 90],
            '90+ days' => [91, 999],
        ];

        foreach ($agingRanges as $label => $range) {
            $count = $overdueInvoices->filter(function ($invoice) use ($range) {
                $days = $invoice->due_date->diffInDays(now());

                return $days >= $range[0] && $days <= $range[1];
            })->count();

            $amount = $overdueInvoices->filter(function ($invoice) use ($range) {
                $days = $invoice->due_date->diffInDays(now());

                return $days >= $range[0] && $days <= $range[1];
            })->sum('balance_due');

            $this->info("{$label}: {$count} invoices, {$amount} Birr");
        }

        return Command::SUCCESS;
    }
}

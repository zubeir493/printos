<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateOverdueInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:update-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update overdue invoice status automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue invoices...');

        // Find all unpaid invoices that are past their due date
        $overdueInvoices = Invoice::where('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'cancelled', 'overdue'])
            ->get();

        $updatedCount = 0;

        foreach ($overdueInvoices as $invoice) {
            // Skip if already paid
            if ($invoice->isPaid()) {
                continue;
            }

            $invoice->update(['status' => 'overdue']);
            $updatedCount++;

            $this->line("Invoice {$invoice->invoice_number} marked as overdue");

            // Log the status change
            Log::info("Invoice {$invoice->invoice_number} marked as overdue", [
                'invoice_id' => $invoice->id,
                'due_date' => $invoice->due_date,
                'partner_id' => $invoice->partner_id,
            ]);
        }

        $this->info("Updated {$updatedCount} invoices to overdue status");

        // Show summary
        $totalOverdue = Invoice::overdue()->count();
        $this->info("Total overdue invoices: {$totalOverdue}");

        return Command::SUCCESS;
    }
}

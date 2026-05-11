<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class UpdateOverdueInvoices extends Command
{
    protected $signature = 'invoices:update-overdue';

    protected $description = 'Update overdue invoice status and notify finance/admin/operations users';

    public function handle(): int
    {
        $this->info('Checking for overdue invoices...');

        $overdueInvoices = Invoice::where('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'cancelled', 'overdue'])
            ->get();

        $recipients = User::whereIn('role', [
            UserRole::Admin->value,
            UserRole::Finance->value,
            UserRole::Operations->value,
        ])->get();

        $updatedCount = 0;

        foreach ($overdueInvoices as $invoice) {
            if ($invoice->isPaid()) {
                continue;
            }

            $invoice->update(['status' => 'overdue']);
            $updatedCount++;

            $this->line("Invoice {$invoice->invoice_number} marked as overdue");

            Log::info("Invoice {$invoice->invoice_number} marked as overdue", [
                'invoice_id' => $invoice->id,
                'due_date' => $invoice->due_date,
                'partner_id' => $invoice->partner_id,
            ]);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new InvoiceOverdueNotification($invoice));
            }
        }

        $this->info("Updated {$updatedCount} invoices to overdue status");

        $totalOverdue = Invoice::overdue()->count();
        $this->info("Total overdue invoices: {$totalOverdue}");

        return Command::SUCCESS;
    }
}

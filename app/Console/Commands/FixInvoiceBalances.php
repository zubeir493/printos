<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Console\Command;

class FixInvoiceBalances extends Command
{
    protected $signature = 'invoices:fix-balances';

    protected $description = 'Recalculate balance_due and status for all invoices based on actual payments';

    public function handle(): int
    {
        $invoices = Invoice::all();

        foreach ($invoices as $invoice) {
            $orderType = match ($invoice->order_type) {
                'sales_order' => SalesOrder::class,
                'purchase_order' => PurchaseOrder::class,
                'job_order' => JobOrder::class,
                default => null,
            };

            if (! $orderType) {
                continue;
            }

            $order = $orderType::query()->find($invoice->order_id);
            $paidAmount = $order
                ? (float) $order->payments()->whereNull('voided_at')->sum('amount')
                : 0.0;

            $balanceDue = max(0, $invoice->total_amount - $paidAmount);

            $status = match (true) {
                $balanceDue <= 0 => 'paid',
                $paidAmount > 0 && $balanceDue < (float) $invoice->total_amount => 'partial',
                default => 'unpaid',
            };

            $invoice->updateQuietly([
                'balance_due' => $balanceDue,
                'status' => $status,
            ]);

            $this->line("Invoice {$invoice->invoice_number}: balance_due={$balanceDue}, status={$status}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}

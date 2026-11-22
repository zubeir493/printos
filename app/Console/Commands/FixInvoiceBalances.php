<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixInvoiceBalances extends Command
{
    protected $signature = 'invoices:fix-balances';

    protected $description = 'Reconcile order-backed invoice balances with recorded payments';

    public function handle(): int
    {
        $invoices = Invoice::query()
            ->whereNotNull('order_id')
            ->whereIn('order_type', ['sales_order', 'purchase_order', 'job_order'])
            ->where('status', '!=', 'cancelled')
            ->lazyById();

        foreach ($invoices as $candidate) {
            $orderType = match ($candidate->order_type) {
                'sales_order' => SalesOrder::class,
                'purchase_order' => PurchaseOrder::class,
                'job_order' => JobOrder::class,
            };

            DB::transaction(function () use ($candidate, $orderType): void {
                $order = $orderType::query()->lockForUpdate()->find($candidate->order_id);
                $invoice = Invoice::query()
                    ->where('order_type', $candidate->order_type)
                    ->where('order_id', $candidate->order_id)
                    ->lockForUpdate()->find($candidate->id);

                if (! $order || ! $invoice || $invoice->status === 'cancelled') {
                    return;
                }

                if (in_array((string) $order->status, [SalesOrder::STATUS_VOID, 'cancelled'], true)) {
                    $invoice->updateQuietly(['status' => 'cancelled', 'balance_due' => 0]);

                    return;
                }

                $paidAmount = (float) $order->payments()
                    ->where('direction', $order instanceof PurchaseOrder ? 'outbound' : 'inbound')
                    ->whereNull('voided_at')
                    ->sum('amount');
                $invoice->syncPaymentBalance($paidAmount);
            }, attempts: 3);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}

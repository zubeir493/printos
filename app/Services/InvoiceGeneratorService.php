<?php

namespace App\Services;

use App\Mail\InvoiceGenerated;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Support\PrivateStorage;
use App\Support\SequentialNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceGeneratorService
{
    /**
     * Generate invoice from SalesOrder
     */
    public function generateFromSalesOrder(SalesOrder $order, array $options = []): array
    {
        // Idempotency guard — never create two invoices for the same order
        $existing = Invoice::where('order_id', $order->id)
            ->where('order_type', 'sales_order')
            ->first();

        if ($existing) {
            return [
                'filename' => $existing->filename,
                'path' => $existing->file_path,
                'invoice_data' => [],
                'pdf' => null,
                'invoice' => $existing,
            ];
        }

        $invoiceNumber = $this->generateInvoiceNumber('SALES');
        $settings = Setting::getSettings();

        $taxAmount = (float) $order->tax_amount;
        $subtotal = (float) $order->subtotal;
        $totalAmount = (float) $order->total;
        $taxCalculations = [
            'total_tax' => $taxAmount,
            'breakdown' => $taxAmount > 0 ? ['VAT' => $taxAmount] : [],
        ];

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => Carbon::now()->format('Y-m-d'),
            'due_date' => $order->due_date ? $order->due_date->format('Y-m-d') : Carbon::now()->addDays($settings->invoice_due_days ?? 30)->format('Y-m-d'),
            'order' => $order,
            'items' => $order->salesOrderItems,
            'payments' => $order->paymentAllocations,
            'company_info' => $this->loadCompanyInformation(),
            'tax_calculations' => $taxCalculations,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'balance_due' => $order->balance,
            'status' => $this->getInvoiceStatus($order),
            'terms' => $settings->invoice_terms,
            'currency_code' => $settings->currency_code ?? 'Birr',
            'currency_symbol' => $settings->currency_symbol ?? 'Birr',
            'options' => array_merge([
                'show_tax_breakdown' => true,
                'show_payment_status' => true,
                'show_terms' => true,
            ], $options),
        ];

        $pdf = Pdf::loadView('invoices.sales-order', ['invoiceData' => $invoiceData])
            ->setPaper('a4')
            ->setOption('defaultFont', 'Arial')
            ->setOption('fontDir', public_path('fonts'))
            ->setOption('fontCache', public_path('fonts'))
            ->setOption('isRemoteEnabled', true);

        $filename = "invoice-{$invoiceNumber}.pdf";
        $path = "invoices/{$filename}";

        Storage::disk(PrivateStorage::diskName())->put($path, $pdf->output());

        // Save invoice to database
        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'sales',
            'order_id' => $order->id,
            'order_type' => 'sales_order',
            'partner_id' => $order->partner_id,
            'invoice_date' => Carbon::now(),
            'due_date' => $order->due_date ?: Carbon::now()->addDays(30),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'balance_due' => $order->balance,
            'status' => $this->getInvoiceStatus($order),
            'filename' => $filename,
            'file_path' => $path,
            'tax_calculations' => $taxCalculations,
            'options' => $options,
        ]);

        return [
            'filename' => $filename,
            'path' => $path,
            'invoice_data' => $invoiceData,
            'pdf' => $pdf,
            'invoice' => $invoice,
        ];
    }

    /**
     * Generate receipt from Payment
     */
    public function generateFromPayment(Payment $payment, array $options = []): array
    {
        $receiptNumber = $this->generateInvoiceNumber('RECEIPT');

        $receiptData = [
            'receipt_number' => $receiptNumber,
            'receipt_date' => $payment->payment_date->format('Y-m-d'),
            'payment' => $payment,
            'allocations' => $payment->paymentAllocations,
            'company_info' => $this->loadCompanyInformation(),
            'options' => array_merge([
                'show_payment_method' => true,
                'show_allocated_orders' => true,
            ], $options),
        ];

        $pdf = Pdf::loadView('invoices.payment-receipt', $receiptData)
            ->setPaper('a4')
            ->setOption('defaultFont', 'Arial')
            ->setOption('fontDir', public_path('fonts'))
            ->setOption('fontCache', public_path('fonts'))
            ->setOption('isRemoteEnabled', true);

        $filename = "receipt-{$receiptNumber}.pdf";
        $path = "receipts/{$filename}";

        Storage::disk(PrivateStorage::diskName())->put($path, $pdf->output());

        return [
            'filename' => $filename,
            'path' => $path,
            'receipt_data' => $receiptData,
            'pdf' => $pdf,
        ];
    }

    /**
     * Generate invoice from PurchaseOrder
     */
    public function generateFromPurchaseOrder(PurchaseOrder $order, array $options = []): array
    {
        // Idempotency guard — never create two invoices for the same order
        $existing = Invoice::where('order_id', $order->id)
            ->where('order_type', 'purchase_order')
            ->first();

        if ($existing) {
            return [
                'filename' => $existing->filename,
                'path' => $existing->file_path,
                'invoice_data' => [],
                'pdf' => null,
                'invoice' => $existing,
            ];
        }

        $invoiceNumber = $this->generateInvoiceNumber('PURCHASE');
        $settings = Setting::getSettings();

        $taxAmount = (float) $order->tax_amount;
        $subtotal = (float) $order->subtotal;
        $totalAmount = (float) $order->total;
        $taxCalculations = [
            'total_tax' => $taxAmount,
            'breakdown' => $taxAmount > 0 ? ['VAT' => $taxAmount] : [],
        ];

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => Carbon::now()->format('Y-m-d'),
            'due_date' => $order->due_date ? $order->due_date->format('Y-m-d') : Carbon::now()->addDays($settings->invoice_due_days ?? 30)->format('Y-m-d'),
            'order' => $order,
            'items' => $order->purchaseOrderItems,
            'payments' => $order->paymentAllocations,
            'company_info' => $this->loadCompanyInformation(),
            'tax_calculations' => $taxCalculations,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'balance_due' => $order->balance,
            'status' => $this->getInvoiceStatus($order),
            'terms' => $settings->invoice_terms,
            'currency_code' => $settings->currency_code ?? 'Birr',
            'currency_symbol' => $settings->currency_symbol ?? 'Birr',
            'options' => array_merge([
                'show_tax_breakdown' => true,
                'show_payment_status' => true,
                'show_terms' => true,
            ], $options),
        ];

        $pdf = Pdf::loadView('invoices.purchase-order', ['invoiceData' => $invoiceData])
            ->setPaper('a4')
            ->setOption('defaultFont', 'Arial')
            ->setOption('fontDir', public_path('fonts'))
            ->setOption('fontCache', public_path('fonts'))
            ->setOption('isRemoteEnabled', true);

        $filename = "purchase-invoice-{$invoiceNumber}.pdf";
        $path = "invoices/{$filename}";

        Storage::disk(PrivateStorage::diskName())->put($path, $pdf->output());

        // Save invoice to database
        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'purchase',
            'order_id' => $order->id,
            'order_type' => 'purchase_order',
            'partner_id' => $order->partner_id,
            'invoice_date' => Carbon::now(),
            'due_date' => $order->due_date ?: Carbon::now()->addDays(30),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'balance_due' => $order->balance,
            'status' => $this->getInvoiceStatus($order),
            'filename' => $filename,
            'file_path' => $path,
            'tax_calculations' => $taxCalculations,
            'options' => $options,
        ]);

        return [
            'filename' => $filename,
            'path' => $path,
            'invoice_data' => $invoiceData,
            'pdf' => $pdf,
            'invoice' => $invoice,
        ];
    }

    /**
     * Generate invoice from JobOrder
     */
    public function generateFromJobOrder(JobOrder $order, array $options = []): array
    {
        // Idempotency guard — never create two invoices for the same order
        $existing = Invoice::where('order_id', $order->id)
            ->where('order_type', 'job_order')
            ->first();

        if ($existing) {
            return [
                'filename' => $existing->filename,
                'path' => $existing->file_path,
                'invoice_data' => [],
                'pdf' => null,
                'invoice' => $existing,
            ];
        }

        $invoiceNumber = $this->generateInvoiceNumber('SERVICE');
        $jobOrderItems = $order->jobOrderTasks()->get();
        $settings = Setting::getSettings();

        $items = $jobOrderItems->map(function ($task) use ($order) {
            $taskCost = $task->task_cost ?: 0;

            return [
                'job_order_number' => $order->job_order_number,
                'service_name' => $task->name,
                'quantity' => $task->quantity ?: 1,
                'unit_price' => (float) $taskCost,
                'total' => (float) $taskCost, // task_cost is already the total, not unit price
            ];
        })->all();

        $actualSubtotal = (float) $order->subtotal;
        $taxAmount = (float) $order->tax_amount;
        $invoiceTotal = (float) $order->total;
        $paidAmount = (float) $order->paid_amount;
        $taxCalculations = [
            'total_tax' => $taxAmount,
            'breakdown' => $taxAmount > 0 ? ['VAT' => $taxAmount] : [],
        ];

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => Carbon::now()->format('Y-m-d'),
            'due_date' => $order->due_date ? $order->due_date->format('Y-m-d') : Carbon::now()->addDays($settings->invoice_due_days ?? 15)->format('Y-m-d'),
            'order' => $order,
            'items' => $items,
            'payments' => $order->paymentAllocations,
            'company_info' => $this->loadCompanyInformation(),
            'customer_info' => [
                'name' => $order->partner?->name ?? 'Internal Job',
                'address' => $order->partner?->address ?? '',
                'phone' => $order->partner?->phone ?? '',
                'email' => $order->partner?->email ?? '',
            ],
            'tax_calculations' => $taxCalculations,
            'subtotal' => $actualSubtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $invoiceTotal,
            'balance_due' => max(0, $invoiceTotal - $paidAmount),
            'status' => $this->getInvoiceStatus($order),
            'notes' => $order->remarks ?? null,
            'terms' => $settings->invoice_terms,
            'currency_code' => $settings->currency_code ?? 'Birr',
            'currency_symbol' => $settings->currency_symbol ?? 'Birr',
            'options' => array_merge([
                'show_service_details' => true,
                'show_tax_breakdown' => true,
            ], $options),
        ];

        $pdf = Pdf::loadView('invoices.job-order', ['invoiceData' => $invoiceData])
            ->setPaper('a4')
            ->setOption('defaultFont', 'Arial')
            ->setOption('fontDir', public_path('fonts'))
            ->setOption('fontCache', public_path('fonts'))
            ->setOption('isRemoteEnabled', true);

        $filename = "service-invoice-{$invoiceNumber}.pdf";
        $path = "invoices/{$filename}";

        Storage::disk(PrivateStorage::diskName())->put($path, $pdf->output());

        // Save invoice to database
        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'service',
            'order_id' => $order->id,
            'order_type' => 'job_order',
            'partner_id' => $order->partner_id,
            'invoice_date' => Carbon::now(),
            'due_date' => $order->due_date ?: Carbon::now()->addDays($settings->invoice_due_days ?? 15),
            'subtotal' => $actualSubtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $invoiceTotal,
            'balance_due' => max(0, $invoiceTotal - $paidAmount),
            'status' => $this->getInvoiceStatus($order),
            'filename' => $filename,
            'file_path' => $path,
            'tax_calculations' => $taxCalculations,
            'options' => $options,
        ]);

        return [
            'filename' => $filename,
            'path' => $path,
            'invoice_data' => $invoiceData,
            'pdf' => $pdf,
            'invoice' => $invoice,
        ];
    }

    /**
     * Generate batch invoice for multiple orders
     */
    public function generateBatchInvoice(array $orders, array $options = []): array
    {
        $invoiceNumber = $this->generateInvoiceNumber('BATCH');
        $settings = Setting::getSettings();

        $allItems = collect();
        $totalSubtotal = 0;
        $taxCalculations = ['total_tax' => 0, 'breakdown' => []];

        foreach ($orders as $order) {
            $allItems = $allItems->merge($order->salesOrderItems);
            $totalSubtotal += $order->subtotal;

            $orderTaxes = $this->calculateTaxes($order->salesOrderItems);
            $taxCalculations['total_tax'] += $orderTaxes['total_tax'];

            foreach ($orderTaxes['breakdown'] as $type => $amount) {
                $taxCalculations['breakdown'][$type] = ($taxCalculations['breakdown'][$type] ?? 0) + $amount;
            }
        }

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => Carbon::now()->format('Y-m-d'),
            'due_date' => Carbon::now()->addDays($settings->invoice_due_days ?? 30)->format('Y-m-d'),
            'orders' => $orders,
            'items' => $allItems,
            'company_info' => $this->loadCompanyInformation(),
            'tax_calculations' => $taxCalculations,
            'subtotal' => $totalSubtotal,
            'tax_amount' => $taxCalculations['total_tax'],
            'total_amount' => $totalSubtotal + $taxCalculations['total_tax'],
            'terms' => $settings->invoice_terms,
            'currency_code' => $settings->currency_code ?? 'Birr',
            'currency_symbol' => $settings->currency_symbol ?? 'Birr',
            'options' => array_merge([
                'show_order_breakdown' => true,
                'show_tax_breakdown' => true,
            ], $options),
        ];

        $pdf = Pdf::loadView('invoices.batch', $invoiceData)
            ->setPaper('a4')
            ->setOption('defaultFont', 'Arial')
            ->setOption('fontDir', public_path('fonts'))
            ->setOption('fontCache', public_path('fonts'))
            ->setOption('isRemoteEnabled', true);

        $filename = "batch-invoice-{$invoiceNumber}.pdf";
        $path = "invoices/{$filename}";

        Storage::disk(PrivateStorage::diskName())->put($path, $pdf->output());

        return [
            'filename' => $filename,
            'path' => $path,
            'invoice_data' => $invoiceData,
            'pdf' => $pdf,
        ];
    }

    /**
     * Send invoice via email
     */
    public function sendInvoiceEmail(array $invoiceData, string $recipientEmail, array $options = []): bool
    {
        try {
            Mail::to($recipientEmail)
                ->queue(new InvoiceGenerated($invoiceData, $options));

            $invoiceNumber = $invoiceData['invoice_data']['invoice_number']
                ?? $invoiceData['receipt_data']['receipt_number']
                ?? 'Document';
            $subjectPrefix = $options['subject_prefix'] ?? 'Document';

            EmailLog::create([
                'recipient_email' => $recipientEmail,
                'subject' => "{$subjectPrefix} #{$invoiceNumber}",
                'message' => $invoiceData['invoice_data']['message'] ?? null,
                'sent_by' => Auth::id(),
                'sent_at' => now(),
            ]);

            Log::info('Invoice email queued successfully', [
                'invoice_number' => $invoiceNumber,
                'recipient' => $recipientEmail,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send invoice', [
                'invoice_number' => $invoiceData['invoice_data']['invoice_number'] ?? 'Unknown',
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate taxes for items
     */
    private function calculateTaxes($items): array
    {
        $taxBreakdown = [];
        $totalTax = 0;

        if (empty($items)) {
            return [
                'total_tax' => 0,
                'breakdown' => $taxBreakdown,
            ];
        }

        foreach ($items as $item) {
            $itemTax = $this->calculateItemTax($item);
            $totalTax += $itemTax['tax_amount'];

            foreach ($itemTax['breakdown'] as $type => $amount) {
                $taxBreakdown[$type] = ($taxBreakdown[$type] ?? 0) + $amount;
            }
        }

        return [
            'total_tax' => $totalTax,
            'breakdown' => $taxBreakdown,
        ];
    }

    /**
     * Calculate taxes for service items
     */
    private function calculateServiceTaxes($order): array
    {
        // Similar to calculateTaxes but for service-specific tax rules
        return $this->calculateTaxes($order->jobOrderTasks()->get());
    }

    /**
     * Calculate tax for individual item
     */
    private function calculateItemTax($item): array
    {
        $taxAmount = 0;
        $breakdown = [];

        // Use the item's total directly if available, otherwise calculate from quantity * unit_price
        $itemTotal = $item->total ?? $item['total'] ?? null;
        if ($itemTotal === null) {
            $quantity = $item->quantity ?? ($item['quantity'] ?? 1);
            $unitPrice = $item->unit_price ?? $item['unit_price'] ?? $item->task_cost ?? 0;
            $itemTotal = $quantity * $unitPrice;
        }

        foreach ($this->loadTaxConfiguration() as $taxType => $rate) {
            if ($this->isTaxApplicable($item, $taxType)) {
                $tax = $itemTotal * $rate;
                $taxAmount += $tax;
                $breakdown[$taxType] = $tax;
            }
        }

        return [
            'tax_amount' => $taxAmount,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Check if tax is applicable to item
     */
    private function isTaxApplicable($item, string $taxType): bool
    {
        // Implement tax applicability rules
        // For now, apply VAT to all items
        return $taxType === 'VAT';
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(string $type): string
    {
        $settings = Setting::getSettings();
        $year = Carbon::now()->format('Y');

        $prefix = match ($type) {
            'SALES' => $settings->invoice_prefix ?? 'INV',
            'PURCHASE' => $settings->invoice_prefix ?? 'INV',
            'SERVICE' => $settings->invoice_prefix ?? 'INV',
            'RECEIPT' => $settings->receipt_prefix ?? 'RCP',
            'BATCH' => 'BATCH',
            default => $settings->invoice_prefix ?? 'INV',
        };

        $sequence = $this->getNextSequence($prefix, $year);

        return "{$prefix}-{$year}-".str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get next sequence number for invoice numbering.
     *
     * Uses a DB advisory lock so concurrent requests cannot generate the same
     * sequence number. The lock key is scoped to prefix+year so different
     * invoice types don't block each other.
     */
    private function getNextSequence(string $prefix, string $year): int
    {
        // Validate inputs to prevent SQL injection
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $prefix) || ! preg_match('/^\d{4}$/', $year)) {
            throw new \InvalidArgumentException('Invalid prefix or year format');
        }

        $number = SequentialNumber::next(
            lockName: "invoices:{$prefix}:{$year}",
            modelClass: Invoice::class,
            column: 'invoice_number',
            prefix: "{$prefix}-{$year}-",
            padding: 6,
            likePattern: "{$prefix}-{$year}-%",
        );

        return (int) str($number)->afterLast('-')->value();
    }

    /**
     * Get invoice status based on order/payment status
     */
    public function getInvoiceStatus($order): string
    {
        if ($order->balance <= 0) {
            return 'paid';
        } elseif ($order->paid_amount > 0) {
            return 'partial';
        } else {
            return 'unpaid';
        }
    }

    /**
     * Load tax configuration from database settings
     */
    private function loadTaxConfiguration(): array
    {
        $settings = Setting::getSettings();

        return $settings->getTaxConfiguration();
    }

    /**
     * Load company information from database settings
     */
    private function loadCompanyInformation(): array
    {
        $settings = Setting::getSettings();

        return $settings->getCompanyInfo();
    }

    /**
     * Get stored invoice path
     */
    public function getInvoicePath(string $filename): string
    {
        $path = "invoices/{$filename}";

        return PrivateStorage::downloadUrl($path, now()->addMinutes(60));
    }

    /**
     * Regenerate PDF for an existing invoice
     */
    public function regeneratePdf(Invoice $invoice): bool
    {
        $order = $invoice->order;
        if (! $order) {
            return false;
        }

        $invoiceData = $this->prepareInvoiceData($invoice, $order);
        $view = $this->getInvoiceView($invoice->order_type);

        $pdf = Pdf::loadView($view, ['invoiceData' => $invoiceData])
            ->setPaper('a4')
            ->setOption('defaultFont', 'Arial')
            ->setOption('fontDir', public_path('fonts'))
            ->setOption('fontCache', public_path('fonts'))
            ->setOption('isRemoteEnabled', true);

        return Storage::disk(PrivateStorage::diskName())->put($invoice->file_path, $pdf->output());
    }

    /**
     * Prepare invoice data for view
     */
    private function prepareInvoiceData(Invoice $invoice, $order): array
    {
        $items = [];
        if ($invoice->order_type === 'sales_order') {
            $items = $order->salesOrderItems;
        } elseif ($invoice->order_type === 'job_order') {
            $items = $order->jobOrderTasks->map(function ($task) use ($order) {
                return [
                    'job_order_number' => $order->job_order_number,
                    'service_name' => $task->name,
                    'quantity' => $task->quantity ?: 1,
                    'unit_price' => (float) $task->task_cost,
                    'total' => (float) $task->task_cost,
                ];
            });
        }

        return [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
            'due_date' => $invoice->due_date->format('Y-m-d'),
            'order' => $order,
            'items' => $items,
            'payments' => $order->paymentAllocations,
            'company_info' => $this->loadCompanyInformation(),
            'tax_calculations' => $invoice->tax_calculations,
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'balance_due' => $order->balance,
            'status' => $this->getInvoiceStatus($order),
            'notes' => $order->remarks ?? null,
            'options' => array_merge([
                'show_tax_breakdown' => true,
                'show_payment_status' => true,
                'show_terms' => true,
            ], $invoice->options ?? []),
        ];
    }

    /**
     * Get invoice view based on order type
     */
    private function getInvoiceView(string $orderType): string
    {
        return match ($orderType) {
            'sales_order' => 'invoices.sales-order',
            'purchase_order' => 'invoices.purchase-order',
            'job_order' => 'invoices.job-order',
            default => 'invoices.sales-order',
        };
    }

    /**
     * Delete stored invoice
     */
    public function deleteInvoice(string $filename): bool
    {
        return Storage::disk(PrivateStorage::diskName())->delete("invoices/{$filename}");
    }
}

<?php

namespace App\Services;

use App\Enums\PaymentTransactionType;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class SalesOrderPaymentService
{
    public function createImmediatePaymentForCashSale(SalesOrder $salesOrder): ?Payment
    {
        $salesOrder->loadMissing('payments', 'salesOrderItems');

        if (! $salesOrder->isCashSale()) {
            return null;
        }

        $salesOrder = $salesOrder->fresh() ?? $salesOrder;
        $amount = (float) $salesOrder->total;

        if ($this->requiresBank($salesOrder->payment_method) && ! $salesOrder->bank_id) {
            throw new \RuntimeException('A bank account is required for bank transfer and cheque sales.');
        }

        if ($amount <= 0 || $salesOrder->payments()->whereNull('voided_at')->exists()) {
            return null;
        }

        return DB::transaction(function () use ($salesOrder, $amount) {
            $payment = Payment::create([
                'partner_id' => $salesOrder->partner_id,
                'amount' => $amount,
                'direction' => 'inbound',
                'transaction_type' => PaymentTransactionType::CASH_SALE_RECEIPT->value,
                'method' => $salesOrder->payment_method ?: 'cash',
                'bank_id' => $salesOrder->bank_id,
                'reference' => $salesOrder->payment_reference ?: 'Immediate receipt for sale '.$salesOrder->order_number,
                'payment_date' => $salesOrder->order_date,
                'payable_type' => SalesOrder::class,
                'payable_id' => $salesOrder->id,
            ]);

            return $payment;
        });
    }

    /**
     * Process multiple payments from SalesOrder form
     */
    public function processMultiplePayments(SalesOrder $salesOrder, array $paymentsData): array
    {
        return DB::transaction(function () use ($salesOrder, $paymentsData) {
            $salesOrder = $salesOrder->newQuery()
                ->lockForUpdate()
                ->findOrFail($salesOrder->getKey());
            $createdPayments = [];
            $totalPayments = collect($paymentsData)->sum('amount');
            $remainingBalance = (float) $salesOrder->total - (float) $salesOrder->paid_amount;

            // Validate total payments don't exceed order total
            if ($totalPayments > $remainingBalance) {
                throw new \Exception('Total payments ('.Money::format($totalPayments).') exceed the remaining balance ('.Money::format($remainingBalance).')');
            }

            foreach ($paymentsData as $paymentData) {
                if (empty($paymentData['amount']) || $paymentData['amount'] <= 0) {
                    continue; // Skip empty payment rows
                }

                if ($this->requiresBank($paymentData['method'] ?? null) && empty($paymentData['bank_id'])) {
                    throw new \RuntimeException('A bank account is required for bank transfer and cheque payments.');
                }

                $payment = $this->createPaymentFromData($salesOrder, $paymentData);

                $createdPayments[] = $payment;
            }

            return $createdPayments;
        });
    }

    /**
     * Create a single payment from payment data
     */
    private function createPaymentFromData(SalesOrder $salesOrder, array $paymentData): Payment
    {
        return Payment::create([
            'partner_id' => $salesOrder->partner_id,
            'amount' => $paymentData['amount'],
            'direction' => 'inbound',
            'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
            'method' => $paymentData['method'],
            'bank_id' => $paymentData['bank_id'] ?? null,
            'reference' => $paymentData['reference'] ?? 'Payment for '.$salesOrder->order_number,
            'payment_date' => $paymentData['payment_date'] ?? $salesOrder->order_date,
            'payable_type' => SalesOrder::class,
            'payable_id' => $salesOrder->id,
        ]);
    }

    private function requiresBank(?string $method): bool
    {
        return in_array($method, ['bank', 'bank_transfer', 'cheque', 'check'], true);
    }
}

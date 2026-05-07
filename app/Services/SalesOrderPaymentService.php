<?php

namespace App\Services;

use App\Enums\PaymentTransactionType;
use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class SalesOrderPaymentService
{
    public function createImmediatePaymentForCashSale(SalesOrder $salesOrder): ?Payment
    {
        $salesOrder->loadMissing('paymentAllocations', 'salesOrderItems');

        if (! $salesOrder->isCashSale()) {
            return null;
        }

        $amount = (float) $salesOrder->salesOrderItems->sum('total');

        if ($amount <= 0) {
            $amount = (float) $salesOrder->fresh()->total;
        }

        if ($amount <= 0 || $salesOrder->paymentAllocations()->exists()) {
            return null;
        }

        return DB::transaction(function () use ($salesOrder, $amount) {
            $nextId = (Payment::max('id') ?? 0) + 1;
            $paymentNumber = 'PAY-SO-'.str_pad($nextId, 6, '0', STR_PAD_LEFT);

            $payment = Payment::create([
                'payment_number' => $paymentNumber,
                'partner_id' => $salesOrder->partner_id,
                'amount' => $amount,
                'direction' => 'inbound',
                'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
                'method' => $salesOrder->payment_method ?: 'cash',
                'reference' => $salesOrder->payment_reference ?: 'Immediate receipt for sale '.$salesOrder->order_number,
                'payment_date' => $salesOrder->order_date,
            ]);

            $salesOrder->paymentAllocations()->create([
                'payment_id' => $payment->id,
                'allocated_amount' => $amount,
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
            $createdPayments = [];
            $totalPayments = collect($paymentsData)->sum('amount');

            // Validate total payments don't exceed order total
            if ($totalPayments > $salesOrder->total) {
                throw new \Exception("Total payments ({$totalPayments} Birr) exceed order total ({$salesOrder->total} Birr)");
            }

            foreach ($paymentsData as $paymentData) {
                if (empty($paymentData['amount']) || $paymentData['amount'] <= 0) {
                    continue; // Skip empty payment rows
                }

                $payment = $this->createPaymentFromData($salesOrder, $paymentData);

                // Create payment allocation
                $salesOrder->paymentAllocations()->create([
                    'payment_id' => $payment->id,
                    'allocated_amount' => $paymentData['amount'],
                ]);

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
        $nextId = (Payment::max('id') ?? 0) + 1;
        $paymentNumber = 'PAY-SO-'.str_pad($nextId, 6, '0', STR_PAD_LEFT);

        return Payment::create([
            'payment_number' => $paymentNumber,
            'partner_id' => $salesOrder->partner_id,
            'amount' => $paymentData['amount'],
            'direction' => 'inbound',
            'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
            'method' => $paymentData['method'],
            'bank_id' => $paymentData['bank_id'] ?? null,
            'reference' => $paymentData['reference'] ?? 'Payment for '.$salesOrder->order_number,
            'payment_date' => $salesOrder->order_date,
        ]);
    }
}

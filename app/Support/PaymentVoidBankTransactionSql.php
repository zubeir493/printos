<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PaymentVoidBankTransactionSql
{
    public static function make(bool $corrected): string
    {
        $id = DB::getDriverName() === 'sqlite' ? "'payment-void-' || payments.id" : "CONCAT('payment-void-', payments.id)";
        $number = DB::getDriverName() === 'sqlite' ? "'VOID-' || payments.payment_number" : "CONCAT('VOID-', payments.payment_number)";
        $amount = '(payments.amount - COALESCE(payments.withholding_amount, 0))';
        $methods = $corrected ? "'bank', 'bank_transfer', 'cheque', 'check'" : "'bank', 'bank_transfer', 'cpo'";

        return " UNION ALL SELECT {$id} id, payments.bank_id, payments.id source_id, 'payment_void' source_type, {$number} transaction_number, payments.transaction_type, CASE WHEN payments.direction = 'inbound' THEN 'outbound' ELSE 'inbound' END direction, {$amount} amount, CASE WHEN payments.direction = 'inbound' THEN -{$amount} ELSE {$amount} END balance_delta, DATE(payments.voided_at) transaction_date, 'posted' status, payments.void_reason reference, partners.name counterparty, NULL related_bank_name, payments.voided_at created_at, payments.voided_at updated_at FROM payments LEFT JOIN partners ON partners.id = payments.partner_id WHERE payments.bank_id IS NOT NULL AND payments.method IN ({$methods}) AND payments.voided_at IS NOT NULL AND payments.deleted_at IS NULL";
    }
}

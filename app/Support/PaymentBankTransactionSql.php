<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PaymentBankTransactionSql
{
    public static function make(bool $corrected): string
    {
        $id = DB::getDriverName() === 'sqlite' ? "'payment-' || payments.id" : "CONCAT('payment-', payments.id)";
        $amount = '(payments.amount - COALESCE(payments.withholding_amount, 0))';
        $methods = $corrected ? "'bank', 'bank_transfer', 'cheque', 'check'" : "'bank', 'bank_transfer', 'cpo'";

        return "SELECT {$id} id, payments.bank_id, payments.id source_id, 'payment' source_type, payments.payment_number transaction_number, payments.transaction_type, payments.direction, {$amount} amount, CASE WHEN payments.direction = 'inbound' THEN {$amount} ELSE -{$amount} END balance_delta, payments.payment_date transaction_date, CASE WHEN payments.voided_at IS NULL THEN 'posted' ELSE 'voided' END status, payments.reference, partners.name counterparty, NULL related_bank_name, payments.created_at, payments.updated_at FROM payments LEFT JOIN partners ON partners.id = payments.partner_id WHERE payments.bank_id IS NOT NULL AND payments.method IN ({$methods}) AND payments.deleted_at IS NULL";
    }
}

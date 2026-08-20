<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class BankTransactionsView
{
    public static function create(bool $includeDeposits = true, bool $correctedPayments = true): void
    {
        $sql = PaymentBankTransactionSql::make($correctedPayments)
            .PaymentVoidBankTransactionSql::make($correctedPayments)
            .TransferBankTransactionsSql::make()
            .($includeDeposits ? DepositBankTransactionsSql::make() : '');

        DB::statement('CREATE VIEW bank_transactions AS '.$sql);
    }
}

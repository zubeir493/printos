<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DepositBankTransactionsSql
{
    public static function make(): string
    {
        $id = self::concat('cash-deposit-', 'cash_deposits.id');
        $reversalId = self::concat('cash-deposit-reversal-', 'cash_deposits.id');
        $reversalNumber = self::concat('REV-', 'cash_deposits.deposit_number');
        $hasIncomeAccount = Schema::hasColumn('cash_deposits', 'income_account_id');
        $sourceAccount = $hasIncomeAccount
            ? 'COALESCE(cash_accounts.name, income_accounts.name)'
            : 'cash_accounts.name';
        $sourceJoins = $hasIncomeAccount
            ? 'LEFT JOIN accounts cash_accounts ON cash_accounts.id = cash_deposits.cash_account_id LEFT JOIN accounts income_accounts ON income_accounts.id = cash_deposits.income_account_id'
            : 'LEFT JOIN accounts cash_accounts ON cash_accounts.id = cash_deposits.cash_account_id';

        return " UNION ALL SELECT {$id} id, cash_deposits.bank_id, cash_deposits.id source_id, 'cash_deposit' source_type, cash_deposits.deposit_number transaction_number, 'cash_deposit' transaction_type, 'inbound' direction, cash_deposits.amount, cash_deposits.amount balance_delta, cash_deposits.deposit_date transaction_date, cash_deposits.status, cash_deposits.reference, {$sourceAccount} counterparty, NULL related_bank_name, cash_deposits.created_at, cash_deposits.updated_at FROM cash_deposits {$sourceJoins} WHERE cash_deposits.status IN ('posted', 'reversed') UNION ALL SELECT {$reversalId} id, cash_deposits.bank_id, cash_deposits.id source_id, 'cash_deposit_reversal' source_type, {$reversalNumber} transaction_number, 'cash_deposit_reversal' transaction_type, 'outbound' direction, cash_deposits.amount, -cash_deposits.amount balance_delta, DATE(cash_deposits.reversed_at) transaction_date, 'posted' status, cash_deposits.reversal_reason reference, {$sourceAccount} counterparty, NULL related_bank_name, cash_deposits.reversed_at created_at, cash_deposits.reversed_at updated_at FROM cash_deposits {$sourceJoins} WHERE cash_deposits.status = 'reversed' AND cash_deposits.reversed_at IS NOT NULL";
    }

    private static function concat(string $prefix, string $column): string
    {
        return DB::getDriverName() === 'sqlite' ? "'{$prefix}' || {$column}" : "CONCAT('{$prefix}', {$column})";
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class TransferBankTransactionsSql
{
    public static function make(): string
    {
        $outId = self::concat('bank-transfer-out-', 'bank_transfers.id');
        $inId = self::concat('bank-transfer-in-', 'bank_transfers.id');

        return " UNION ALL SELECT {$outId} id, bank_transfers.from_bank_id bank_id, bank_transfers.id source_id, 'bank_transfer' source_type, bank_transfers.transfer_number transaction_number, 'transfer_out' transaction_type, 'outbound' direction, bank_transfers.amount, -bank_transfers.amount balance_delta, bank_transfers.transfer_date transaction_date, bank_transfers.status, bank_transfers.reference, NULL counterparty, to_banks.name related_bank_name, bank_transfers.created_at, bank_transfers.updated_at FROM bank_transfers INNER JOIN banks to_banks ON to_banks.id = bank_transfers.to_bank_id WHERE bank_transfers.status = 'completed' UNION ALL SELECT {$inId} id, bank_transfers.to_bank_id bank_id, bank_transfers.id source_id, 'bank_transfer' source_type, bank_transfers.transfer_number transaction_number, 'transfer_in' transaction_type, 'inbound' direction, bank_transfers.amount, bank_transfers.amount balance_delta, bank_transfers.transfer_date transaction_date, bank_transfers.status, bank_transfers.reference, NULL counterparty, from_banks.name related_bank_name, bank_transfers.created_at, bank_transfers.updated_at FROM bank_transfers INNER JOIN banks from_banks ON from_banks.id = bank_transfers.from_bank_id WHERE bank_transfers.status = 'completed'";
    }

    private static function concat(string $prefix, string $column): string
    {
        return DB::getDriverName() === 'sqlite' ? "'{$prefix}' || {$column}" : "CONCAT('{$prefix}', {$column})";
    }
}

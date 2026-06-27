<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS bank_transactions');

        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('withholding_amount', 15, 2)->default(0)->after('amount');
        });

        $this->createBankTransactionsView();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS bank_transactions');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('withholding_amount');
        });

        $this->createBankTransactionsView(false);
    }

    private function createBankTransactionsView(bool $withholding = true): void
    {
        $paymentAmount = $withholding
            ? '(payments.amount - COALESCE(payments.withholding_amount, 0))'
            : 'payments.amount';
        $methodList = "'bank', 'bank_transfer', 'cpo'";

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<SQL
            CREATE VIEW bank_transactions AS
                SELECT
                    'payment-' || payments.id AS id,
                    payments.bank_id,
                    payments.id AS source_id,
                    'payment' AS source_type,
                    payments.payment_number AS transaction_number,
                    payments.transaction_type AS transaction_type,
                    payments.direction AS direction,
                    {$paymentAmount} AS amount,
                    CASE
                        WHEN payments.direction = 'inbound' THEN {$paymentAmount}
                        ELSE -{$paymentAmount}
                    END AS balance_delta,
                    payments.payment_date AS transaction_date,
                    CASE
                        WHEN payments.voided_at IS NULL THEN 'posted'
                        ELSE 'voided'
                    END AS status,
                    payments.reference,
                    partners.name AS counterparty,
                    NULL AS related_bank_name,
                    payments.created_at,
                    payments.updated_at
                FROM payments
                LEFT JOIN partners ON partners.id = payments.partner_id
                WHERE payments.bank_id IS NOT NULL
                    AND payments.method IN ({$methodList})
                    AND payments.deleted_at IS NULL

                UNION ALL

                SELECT
                    'payment-void-' || payments.id AS id,
                    payments.bank_id,
                    payments.id AS source_id,
                    'payment_void' AS source_type,
                    'VOID-' || payments.payment_number AS transaction_number,
                    payments.transaction_type AS transaction_type,
                    CASE
                        WHEN payments.direction = 'inbound' THEN 'outbound'
                        ELSE 'inbound'
                    END AS direction,
                    {$paymentAmount} AS amount,
                    CASE
                        WHEN payments.direction = 'inbound' THEN -{$paymentAmount}
                        ELSE {$paymentAmount}
                    END AS balance_delta,
                    DATE(payments.voided_at) AS transaction_date,
                    'posted' AS status,
                    payments.void_reason AS reference,
                    partners.name AS counterparty,
                    NULL AS related_bank_name,
                    payments.voided_at AS created_at,
                    payments.voided_at AS updated_at
                FROM payments
                LEFT JOIN partners ON partners.id = payments.partner_id
                WHERE payments.bank_id IS NOT NULL
                    AND payments.method IN ({$methodList})
                    AND payments.voided_at IS NOT NULL
                    AND payments.deleted_at IS NULL

                UNION ALL

                SELECT
                    'bank-transfer-out-' || bank_transfers.id AS id,
                    bank_transfers.from_bank_id AS bank_id,
                    bank_transfers.id AS source_id,
                    'bank_transfer' AS source_type,
                    bank_transfers.transfer_number AS transaction_number,
                    'transfer_out' AS transaction_type,
                    'outbound' AS direction,
                    bank_transfers.amount AS amount,
                    -bank_transfers.amount AS balance_delta,
                    bank_transfers.transfer_date AS transaction_date,
                    bank_transfers.status AS status,
                    bank_transfers.reference,
                    NULL AS counterparty,
                    to_banks.name AS related_bank_name,
                    bank_transfers.created_at,
                    bank_transfers.updated_at
                FROM bank_transfers
                INNER JOIN banks AS to_banks ON to_banks.id = bank_transfers.to_bank_id
                WHERE bank_transfers.status = 'completed'

                UNION ALL

                SELECT
                    'bank-transfer-in-' || bank_transfers.id AS id,
                    bank_transfers.to_bank_id AS bank_id,
                    bank_transfers.id AS source_id,
                    'bank_transfer' AS source_type,
                    bank_transfers.transfer_number AS transaction_number,
                    'transfer_in' AS transaction_type,
                    'inbound' AS direction,
                    bank_transfers.amount AS amount,
                    bank_transfers.amount AS balance_delta,
                    bank_transfers.transfer_date AS transaction_date,
                    bank_transfers.status AS status,
                    bank_transfers.reference,
                    NULL AS counterparty,
                    from_banks.name AS related_bank_name,
                    bank_transfers.created_at,
                    bank_transfers.updated_at
                FROM bank_transfers
                INNER JOIN banks AS from_banks ON from_banks.id = bank_transfers.from_bank_id
                WHERE bank_transfers.status = 'completed'
            SQL);

            return;
        }

        DB::statement(<<<SQL
            CREATE VIEW bank_transactions AS
                SELECT
                    CONCAT('payment-', payments.id) AS id,
                    payments.bank_id,
                    payments.id AS source_id,
                    'payment' AS source_type,
                    payments.payment_number AS transaction_number,
                    payments.transaction_type AS transaction_type,
                    payments.direction AS direction,
                    {$paymentAmount} AS amount,
                    CASE
                        WHEN payments.direction = 'inbound' THEN {$paymentAmount}
                        ELSE -{$paymentAmount}
                    END AS balance_delta,
                    payments.payment_date AS transaction_date,
                    CASE
                        WHEN payments.voided_at IS NULL THEN 'posted'
                        ELSE 'voided'
                    END AS status,
                    payments.reference,
                    partners.name AS counterparty,
                    NULL AS related_bank_name,
                    payments.created_at,
                    payments.updated_at
                FROM payments
                LEFT JOIN partners ON partners.id = payments.partner_id
                WHERE payments.bank_id IS NOT NULL
                    AND payments.method IN ({$methodList})
                    AND payments.deleted_at IS NULL

                UNION ALL

                SELECT
                    CONCAT('payment-void-', payments.id) AS id,
                    payments.bank_id,
                    payments.id AS source_id,
                    'payment_void' AS source_type,
                    CONCAT('VOID-', payments.payment_number) AS transaction_number,
                    payments.transaction_type AS transaction_type,
                    CASE
                        WHEN payments.direction = 'inbound' THEN 'outbound'
                        ELSE 'inbound'
                    END AS direction,
                    {$paymentAmount} AS amount,
                    CASE
                        WHEN payments.direction = 'inbound' THEN -{$paymentAmount}
                        ELSE {$paymentAmount}
                    END AS balance_delta,
                    DATE(payments.voided_at) AS transaction_date,
                    'posted' AS status,
                    payments.void_reason AS reference,
                    partners.name AS counterparty,
                    NULL AS related_bank_name,
                    payments.voided_at AS created_at,
                    payments.voided_at AS updated_at
                FROM payments
                LEFT JOIN partners ON partners.id = payments.partner_id
                WHERE payments.bank_id IS NOT NULL
                    AND payments.method IN ({$methodList})
                    AND payments.voided_at IS NOT NULL
                    AND payments.deleted_at IS NULL

                UNION ALL

                SELECT
                    CONCAT('bank-transfer-out-', bank_transfers.id) AS id,
                    bank_transfers.from_bank_id AS bank_id,
                    bank_transfers.id AS source_id,
                    'bank_transfer' AS source_type,
                    bank_transfers.transfer_number AS transaction_number,
                    'transfer_out' AS transaction_type,
                    'outbound' AS direction,
                    bank_transfers.amount AS amount,
                    -bank_transfers.amount AS balance_delta,
                    bank_transfers.transfer_date AS transaction_date,
                    bank_transfers.status AS status,
                    bank_transfers.reference,
                    NULL AS counterparty,
                    to_banks.name AS related_bank_name,
                    bank_transfers.created_at,
                    bank_transfers.updated_at
                FROM bank_transfers
                INNER JOIN banks AS to_banks ON to_banks.id = bank_transfers.to_bank_id
                WHERE bank_transfers.status = 'completed'

                UNION ALL

                SELECT
                    CONCAT('bank-transfer-in-', bank_transfers.id) AS id,
                    bank_transfers.to_bank_id AS bank_id,
                    bank_transfers.id AS source_id,
                    'bank_transfer' AS source_type,
                    bank_transfers.transfer_number AS transaction_number,
                    'transfer_in' AS transaction_type,
                    'inbound' AS direction,
                    bank_transfers.amount AS amount,
                    bank_transfers.amount AS balance_delta,
                    bank_transfers.transfer_date AS transaction_date,
                    bank_transfers.status AS status,
                    bank_transfers.reference,
                    NULL AS counterparty,
                    from_banks.name AS related_bank_name,
                    bank_transfers.created_at,
                    bank_transfers.updated_at
                FROM bank_transfers
                INNER JOIN banks AS from_banks ON from_banks.id = bank_transfers.from_bank_id
                WHERE bank_transfers.status = 'completed'
        SQL);
    }
};

<?php

use App\Support\BankTransactionsView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();

        DB::table('accounts')->updateOrInsert(
            ['code' => '1020'],
            [
                'name' => 'Other Cash Sources',
                'type' => 'Asset',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        DB::statement('DROP VIEW IF EXISTS bank_transactions');
        BankTransactionsView::create();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS bank_transactions');

        DB::table('accounts')
            ->where('code', '1020')
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('journal_items')
                ->whereColumn('journal_items.account_id', 'accounts.id'))
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('cash_deposits')
                ->whereColumn('cash_deposits.cash_account_id', 'accounts.id'))
            ->delete();

        BankTransactionsView::create();
    }
};

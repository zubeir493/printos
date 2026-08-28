<?php

use App\Support\BankTransactionsView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();

        DB::statement('DROP VIEW IF EXISTS bank_transactions');

        DB::table('accounts')->updateOrInsert(
            ['code' => '4300'],
            [
                'name' => 'Other Income',
                'type' => 'Revenue',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        Schema::table('cash_deposits', function (Blueprint $table): void {
            $table->string('deposit_type')->default('cash_transfer')->after('deposit_number');
            $table->foreignId('income_account_id')
                ->nullable()
                ->after('cash_account_id')
                ->constrained('accounts')
                ->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->change();
        });

        BankTransactionsView::create();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS bank_transactions');

        Schema::table('cash_deposits', function (Blueprint $table): void {
            $table->dropForeign(['income_account_id']);
            $table->dropColumn(['deposit_type', 'income_account_id']);
            $table->foreignId('cash_account_id')->nullable(false)->change();
        });

        DB::table('accounts')
            ->where('code', '4300')
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('journal_items')
                ->whereColumn('journal_items.account_id', 'accounts.id'))
            ->delete();

        BankTransactionsView::create();
    }
};

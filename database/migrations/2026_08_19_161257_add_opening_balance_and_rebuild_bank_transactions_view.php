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
        DB::statement('DROP VIEW IF EXISTS bank_transactions');
        Schema::table('banks', fn (Blueprint $table) => $table->decimal('opening_balance', 15, 2)->nullable()->after('current_balance'));
        BankTransactionsView::create();

        DB::table('banks')->orderBy('id')->each(function (object $bank): void {
            $movement = (float) DB::table('bank_transactions')->where('bank_id', $bank->id)->sum('balance_delta');
            DB::table('banks')->where('id', $bank->id)->update([
                'opening_balance' => round((float) $bank->current_balance - $movement, 2),
            ]);
        });
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS bank_transactions');
        Schema::table('banks', fn (Blueprint $table) => $table->dropColumn('opening_balance'));
        BankTransactionsView::create(includeDeposits: false, correctedPayments: false);
    }
};

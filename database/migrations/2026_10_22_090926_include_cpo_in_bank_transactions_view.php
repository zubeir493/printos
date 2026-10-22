<?php

use App\Support\BankTransactionsView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS bank_transactions');
        BankTransactionsView::create();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS bank_transactions');
        BankTransactionsView::create();
    }
};

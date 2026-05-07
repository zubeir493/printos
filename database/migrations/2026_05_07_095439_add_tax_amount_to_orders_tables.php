<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sales_orders: has subtotal + total → add tax_amount between them
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('tax_amount', 15, 2)->default(0)->after('subtotal');
        });

        // purchase_orders: has subtotal only → add tax_amount + total
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('tax_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('total', 12, 2)->default(0)->after('tax_amount');
        });

        // job_orders: has total_price (pre-tax subtotal) → add tax_amount
        Schema::table('job_orders', function (Blueprint $table) {
            $table->decimal('tax_amount', 12, 2)->default(0)->after('total_price');
        });

        // Backfill existing rows: tax = subtotal/total_price * vat_rate from settings
        // We read vat_rate directly to avoid booting the app in the migration
        $vatRate = DB::table('settings')->value('vat_rate') ?? 15;
        $vatEnabled = DB::table('settings')->value('vat_enabled') ?? true;

        if ($vatEnabled) {
            $rate = $vatRate / 100;

            DB::table('sales_orders')->update([
                'tax_amount' => DB::raw("ROUND(subtotal * {$rate}, 2)"),
                'total' => DB::raw('ROUND(subtotal * '.(1 + $rate).', 2)'),
            ]);

            DB::table('purchase_orders')->update([
                'tax_amount' => DB::raw("ROUND(subtotal * {$rate}, 2)"),
                'total' => DB::raw('ROUND(subtotal * '.(1 + $rate).', 2)'),
            ]);

            DB::table('job_orders')->update([
                'tax_amount' => DB::raw("ROUND(total_price * {$rate}, 2)"),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'total']);
        });

        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });
    }
};

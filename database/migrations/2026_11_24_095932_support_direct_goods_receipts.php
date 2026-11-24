<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->change();
            $table->foreignId('supplier_id')->nullable()->constrained('partners');
            $table->string('source_reference', 120)->nullable();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->foreignId('purchase_order_item_id')->nullable()->change();
            $table->foreignId('inventory_item_id')->nullable()->constrained();
            $table->decimal('unit_cost', 15, 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('goods_receipts')->whereNull('purchase_order_id')->exists()) {
            throw new RuntimeException('Direct goods receipts exist. Restore a database backup to roll back this migration.');
        }

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropColumn('unit_cost');
            $table->foreignId('purchase_order_item_id')->nullable(false)->change();
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('source_reference');
            $table->foreignId('purchase_order_id')->nullable(false)->change();
        });
    }
};

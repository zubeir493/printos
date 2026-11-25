<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->decimal('short_close_subtotal', 15, 2)->nullable();
            $table->decimal('short_close_tax', 15, 2)->nullable();
        });
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->string('direct_reference_key', 64)->nullable()->unique();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->text('reversal_reason')->nullable();
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('goods_receipt_id')->nullable()->unique()->constrained();
        });
        Schema::table('maintenance_store_entries', function (Blueprint $table): void {
            $table->decimal('lost_quantity', 15, 2)->default(0);
            $table->decimal('damaged_quantity', 15, 2)->default(0);
        });
        Schema::table('stock_adjustment_items', fn (Blueprint $table) => $table->boolean('is_imported_count')->default(false));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_adjustment_items', fn (Blueprint $table) => $table->dropColumn('is_imported_count'));
        Schema::table('maintenance_store_entries', fn (Blueprint $table) => $table->dropColumn(['lost_quantity', 'damaged_quantity']));
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['goods_receipt_id']);
            $table->dropConstrainedForeignId('goods_receipt_id');
        });
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropUnique(['direct_reference_key']);
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['direct_reference_key', 'reversed_at', 'reversal_reason']);
        });
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropColumn(['short_close_subtotal', 'short_close_tax']));
    }
};

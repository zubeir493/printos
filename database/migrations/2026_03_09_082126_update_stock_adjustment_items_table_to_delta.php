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
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            if (Schema::hasColumn('stock_adjustment_items', 'counted_quantity')) {
                $table->renameColumn('counted_quantity', 'adjustment_quantity');
            }
            if (! Schema::hasColumn('stock_adjustment_items', 'new_quantity')) {
                $table->decimal('new_quantity', 15, 2)->default(0)->after('difference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->renameColumn('adjustment_quantity', 'counted_quantity');
            $table->dropColumn('new_quantity');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove any duplicate rows before adding the constraint.
        // Keep the row with the highest id (most recent) for each pair.
        // Uses a subquery approach compatible with both MySQL and SQLite.
        $duplicateIds = DB::table('inventory_balances as ib1')
            ->select('ib1.id')
            ->join('inventory_balances as ib2', function ($join) {
                $join->on('ib1.inventory_item_id', '=', 'ib2.inventory_item_id')
                    ->on('ib1.warehouse_id', '=', 'ib2.warehouse_id')
                    ->whereColumn('ib1.id', '<', 'ib2.id');
            })
            ->pluck('id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('inventory_balances')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->unique(['inventory_item_id', 'warehouse_id'], 'inventory_balances_item_warehouse_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->dropUnique('inventory_balances_item_warehouse_unique');
        });
    }
};

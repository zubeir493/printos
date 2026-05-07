<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            // Add subtotal (mirrors total_price) and total (subtotal + tax_amount)
            $table->decimal('subtotal', 12, 2)->default(0)->after('advance_amount');
            $table->decimal('total', 12, 2)->default(0)->after('tax_amount');
        });

        // Backfill: subtotal = total_price, total = total_price + tax_amount
        DB::table('job_orders')->update([
            'subtotal' => DB::raw('total_price'),
            'total' => DB::raw('total_price + tax_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'total']);
        });
    }
};

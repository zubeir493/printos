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
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('sales_tax_code', 16)->default('standard');
            $table->boolean('production_valued')->default(false);
        });
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->string('sales_tax_code', 16)->default('standard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['sales_tax_code', 'production_valued']);
        });
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('sales_tax_code');
        });
    }
};

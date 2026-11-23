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
        Schema::table('job_orders', function (Blueprint $table) {
            $table->boolean('uses_production_ticket')->default(false);
            $table->date('production_due_date')->nullable();
            $table->date('proof_due_date')->nullable();
            $table->foreignId('cost_estimate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->unsignedInteger('recorded_output_quantity')->default(0);
            $table->json('estimated_production_costs')->nullable();
            $table->json('actual_production_costs')->nullable();
            $table->foreignId('cost_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cost_reviewed_at')->nullable();
            $table->timestamp('stock_received_at')->nullable();
            $table->string('cost_calc_file')->nullable()->default('legacy-cost-calculation')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('job_orders')->whereNull('cost_calc_file')->update(['cost_calc_file' => 'legacy-cost-calculation']);
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_estimate_id');
            $table->dropConstrainedForeignId('production_warehouse_id');
            $table->dropConstrainedForeignId('cost_reviewed_by');
            $table->dropColumn([
                'uses_production_ticket', 'production_due_date', 'proof_due_date', 'recorded_output_quantity',
                'estimated_production_costs', 'actual_production_costs', 'cost_reviewed_at', 'stock_received_at',
            ]);
            $table->string('cost_calc_file')->default('legacy-cost-calculation')->change();
        });
    }
};

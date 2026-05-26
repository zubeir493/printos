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
            $table->foreignId('proforma_id')
                ->nullable()
                ->after('id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();
        });

        $jobOrders = DB::table('job_orders')->orderBy('id')->get();

        foreach ($jobOrders as $jobOrder) {
            $proformaId = DB::table('proformas')->insertGetId([
                'proforma_number' => 'PF-LEGACY-'.str_pad((string) $jobOrder->id, 6, '0', STR_PAD_LEFT),
                'partner_id' => $jobOrder->partner_id,
                'job_type' => $jobOrder->job_type,
                'services' => $jobOrder->services,
                'issue_date' => $jobOrder->submission_date,
                'expiry_date' => $jobOrder->due_date ?? now()->addDays(30)->toDateString(),
                'remarks' => $jobOrder->remarks,
                'subtotal' => $jobOrder->subtotal ?? 0,
                'tax_amount' => $jobOrder->tax_amount ?? 0,
                'total' => $jobOrder->total ?? 0,
                'status' => 'job_order_created',
                'created_at' => $jobOrder->created_at,
                'updated_at' => now(),
            ]);

            DB::table('job_order_tasks')
                ->where('job_order_id', $jobOrder->id)
                ->orderBy('id')
                ->get()
                ->each(function ($task) use ($proformaId): void {
                    DB::table('proforma_tasks')->insert([
                        'proforma_id' => $proformaId,
                        'name' => $task->name,
                        'quantity' => max(1, (int) $task->quantity),
                        'size' => $task->size,
                        'unit_price' => $task->quantity > 0 ? round(((float) $task->task_cost) / (int) $task->quantity, 2) : (float) $task->task_cost,
                        'task_cost' => $task->task_cost,
                        'paper' => $task->paper,
                        'deliverables' => $task->deliverables,
                        'instructions' => $task->instructions,
                        'created_at' => $task->created_at,
                        'updated_at' => now(),
                    ]);
                });

            DB::table('job_orders')
                ->where('id', $jobOrder->id)
                ->update(['proforma_id' => $proformaId]);
        }

        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('cost_calc_file');
            $table->foreignId('proforma_id')
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->string('cost_calc_file')->default('legacy-proforma');
            $table->dropForeign(['proforma_id']);
            $table->dropColumn('proforma_id');
        });
    }
};

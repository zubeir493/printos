<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('job_orders')->exists() || DB::table('job_order_tasks')->exists()) {
            throw new RuntimeException('Job-order consolidation requires an empty database. Back up any needed data before rebuilding with migrate:fresh --seed.');
        }
        Schema::table('job_orders', function (Blueprint $table): void {
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('size')->nullable();
            $table->json('paper')->nullable();
            $table->json('deliverables')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('designer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('typist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('proforma_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('proforma_task_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('output_inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            $table->timestamp('design_completed_at')->nullable();
            $table->timestamp('production_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('draft')->change();
            $table->index(['status', 'submission_date']);
            $table->index(['designer_id', 'status']);
            $table->index(['typist_id', 'status']);
        });
        foreach (['dispatch_items', 'production_plan_items', 'material_requests', 'text_files', 'dielines'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['job_order_task_id']);
                $table->renameColumn('job_order_task_id', 'job_order_id');
            });
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $foreign = $table->foreign('job_order_id')->references('id')->on('job_orders');
                if ($name === 'dielines') {
                    $foreign->nullOnDelete();
                } elseif (in_array($name, ['text_files', 'material_requests'])) {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->restrictOnDelete();
                }
            });
        }
        Schema::table('artworks', function (Blueprint $table): void {
            $table->dropForeign(['job_order_task_id']);
            $table->dropColumn('job_order_task_id');
        });
        Schema::table('dispatch_items', function (Blueprint $table): void {
            $table->unique(['dispatch_id', 'job_order_id']);
        });
        Schema::drop('job_order_tasks');
    }

    public function down(): void
    {
        throw new RuntimeException('Job-order consolidation cannot be rolled back automatically. Restore a database backup with the previous application version.');
    }
};

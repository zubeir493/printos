<?php

namespace App\Services\JobOrders;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LegacyJobOrderGroupSchema
{
    public static function ensure(): void
    {
        if (! Schema::hasTable('legacy_job_order_groups')) {
            Schema::create('legacy_job_order_groups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('legacy_parent_id')->unique();
                $table->string('original_job_order_number')->nullable();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->decimal('advance_amount', 12, 2)->default(0);
                $table->boolean('advance_paid')->default(false);
                $table->string('status')->nullable();
                $table->json('original_data');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('legacy_job_order_items')) {
            Schema::create('legacy_job_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legacy_job_order_group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_order_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('legacy_task_id')->nullable()->unique();
                $table->unsignedInteger('task_order');
                $table->decimal('task_cost', 12, 2)->default(0);
                $table->integer('original_quantity')->nullable();
                $table->json('task_snapshot')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('legacy_job_order_payment_allocations')) {
            Schema::create('legacy_job_order_payment_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legacy_job_order_group_id')->constrained(indexName: 'legacy_payment_allocation_group_fk')->cascadeOnDelete();
                $table->foreignId('job_order_id')->constrained(indexName: 'legacy_payment_allocation_job_fk')->cascadeOnDelete();
                $table->foreignId('payment_id')->constrained(indexName: 'legacy_payment_allocation_payment_fk')->cascadeOnDelete();
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('withholding_amount', 12, 2)->default(0);
                $table->timestamps();
                $table->unique(['payment_id', 'job_order_id'], 'legacy_job_payment_allocation_unique');
            });
        } else {
            self::ensureAllocationConstraints();
        }

        if (Schema::hasTable('job_orders') && ! Schema::hasColumn('job_orders', 'legacy_job_order_group_id')) {
            Schema::table('job_orders', function (Blueprint $table): void {
                $table->foreignId('legacy_job_order_group_id')->nullable()
                    ->constrained('legacy_job_order_groups')->nullOnDelete();
            });
        }
    }

    private static function ensureAllocationConstraints(): void
    {
        $tableName = 'legacy_job_order_payment_allocations';
        $constrainedColumns = collect(Schema::getForeignKeys($tableName))->pluck('columns.0');
        $hasUniqueIndex = Schema::hasIndex($tableName, ['payment_id', 'job_order_id'], 'unique');

        Schema::table($tableName, function (Blueprint $table) use ($constrainedColumns, $hasUniqueIndex): void {
            foreach ([
                'legacy_job_order_group_id' => ['legacy_job_order_groups', 'legacy_payment_allocation_group_fk'],
                'job_order_id' => ['job_orders', 'legacy_payment_allocation_job_fk'],
                'payment_id' => ['payments', 'legacy_payment_allocation_payment_fk'],
            ] as $column => [$foreignTable, $name]) {
                if (! $constrainedColumns->contains($column)) {
                    $table->foreign($column, $name)->references('id')->on($foreignTable)->cascadeOnDelete();
                }
            }
            if (! $hasUniqueIndex) {
                $table->unique(['payment_id', 'job_order_id'], 'legacy_job_payment_allocation_unique');
            }
        });
    }
}

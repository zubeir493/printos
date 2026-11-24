<?php

use App\Services\JobOrders\LegacyJobOrderConsolidator;
use App\Services\JobOrders\LegacyJobOrderGroupSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        LegacyJobOrderGroupSchema::ensure();

        Schema::table('job_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_orders', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('job_orders', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1);
            }
            if (! Schema::hasColumn('job_orders', 'size')) {
                $table->string('size')->nullable();
            }
            if (! Schema::hasColumn('job_orders', 'paper')) {
                $table->json('paper')->nullable();
            }
            if (! Schema::hasColumn('job_orders', 'deliverables')) {
                $table->json('deliverables')->nullable();
            }
            if (! Schema::hasColumn('job_orders', 'instructions')) {
                $table->text('instructions')->nullable();
            }
            if (! Schema::hasColumn('job_orders', 'designer_id')) {
                $table->foreignId('designer_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('job_orders', 'typist_id')) {
                $table->foreignId('typist_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('job_orders', 'proforma_id')) {
                $table->foreignId('proforma_id')->nullable()->constrained()->restrictOnDelete();
            }
            if (! Schema::hasColumn('job_orders', 'proforma_task_id')) {
                $table->foreignId('proforma_task_id')->nullable()->unique()->constrained()->restrictOnDelete();
            }
            if (! Schema::hasColumn('job_orders', 'output_inventory_item_id')) {
                $table->foreignId('output_inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            }
            if (! Schema::hasColumn('job_orders', 'design_completed_at')) {
                $table->timestamp('design_completed_at')->nullable();
            }
            if (! Schema::hasColumn('job_orders', 'production_completed_at')) {
                $table->timestamp('production_completed_at')->nullable();
            }
            if (! Schema::hasColumn('job_orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });

        $this->ensureIndex('job_orders', ['status', 'submission_date'], 'job_orders_status_submission_date_index');
        $this->ensureIndex('job_orders', ['designer_id', 'status'], 'job_orders_designer_id_status_index');
        $this->ensureIndex('job_orders', ['typist_id', 'status'], 'job_orders_typist_id_status_index');

        if (Schema::getColumnType('job_orders', 'status') === 'enum') {
            Schema::table('job_orders', function (Blueprint $table): void {
                $table->string('status')->default('draft')->change();
            });
        }

        app(LegacyJobOrderConsolidator::class)->convert();

        $this->ensureUniqueIndex('dispatch_items', ['dispatch_id', 'job_order_id'], 'dispatch_items_dispatch_id_job_order_id_unique');

        $nameColumn = collect(Schema::getColumns('job_orders'))->firstWhere('name', 'name');
        if ($nameColumn && ($nameColumn['nullable'] ?? false)) {
            Schema::table('job_orders', function (Blueprint $table): void {
                $table->string('name')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Job-order consolidation cannot be rolled back automatically. Legacy parent and task records are archived; restore a database backup to return to the previous schema.');
    }

    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if (! collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['columns'] ?? []) === $columns)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    private function ensureUniqueIndex(string $table, array $columns, string $name): void
    {
        $indexes = collect(Schema::getIndexes($table));
        if ($indexes->contains(fn (array $index): bool => ($index['columns'] ?? []) === $columns && ($index['unique'] ?? false))) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
    }
};

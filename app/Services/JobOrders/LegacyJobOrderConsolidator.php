<?php

namespace App\Services\JobOrders;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LegacyJobOrderConsolidator
{
    private const LEGACY_TASK_MODEL = 'App\\Models\\JobOrderTask';

    private const JOB_ORDER_MODEL = 'App\\Models\\JobOrder';

    public function convert(): void
    {
        if (! Schema::hasTable('job_orders') || ! Schema::hasTable('legacy_job_order_groups')) {
            return;
        }

        if (Schema::hasTable('job_order_tasks')) {
            $columns = Schema::getColumnListing('job_orders');

            foreach (DB::table('job_orders')->orderBy('id')->get() as $parent) {
                if (Schema::hasColumn('job_orders', 'legacy_job_order_group_id') && $parent->legacy_job_order_group_id) {
                    continue;
                }

                $this->convertParent((array) $parent, $columns);
            }
        }

        $this->remapTaskReferences();
        $this->archiveLegacyTaskTable();
    }

    private function convertParent(array $parent, array $columns): void
    {
        $parentId = (int) $parent['id'];
        $tasks = DB::table('job_order_tasks')->where('job_order_id', $parentId)->orderBy('id')->get();

        DB::transaction(function () use ($parent, $parentId, $tasks, $columns): void {
            $groupId = DB::table('legacy_job_order_groups')->where('legacy_parent_id', $parentId)->value('id');

            if (! $groupId) {
                $groupId = DB::table('legacy_job_order_groups')->insertGetId([
                    'legacy_parent_id' => $parentId,
                    'original_job_order_number' => $parent['job_order_number'] ?? null,
                    'subtotal' => $parent['subtotal'] ?? 0,
                    'tax_amount' => $parent['tax_amount'] ?? 0,
                    'total' => $parent['total'] ?? 0,
                    'advance_amount' => $parent['advance_amount'] ?? 0,
                    'advance_paid' => (bool) ($parent['advance_paid'] ?? false),
                    'status' => $parent['status'] ?? null,
                    'original_data' => json_encode($parent, JSON_THROW_ON_ERROR),
                    'created_at' => $parent['created_at'] ?? now(),
                    'updated_at' => now(),
                ]);
            }

            if ($tasks->isEmpty()) {
                $this->convertTasklessParent($parent, (int) $groupId);
                $this->allocatePayments($parentId, (int) $groupId, [$parentId], [(float) ($parent['total'] ?? 0)]);

                return;
            }

            $taskCosts = $tasks->map(fn (object $task): int => (int) round((float) ($task->task_cost ?? 0) * 100))->all();
            $parentSubtotal = (int) round((float) ($parent['subtotal'] ?? 0) * 100);
            $taskSubtotal = array_sum($taskCosts);
            $subtotals = $taskSubtotal === $parentSubtotal && min($taskCosts) >= 0
                ? $taskCosts
                : $this->distributeCents($parentSubtotal, array_map(fn (int $cost): float => max(0, $cost), $taskCosts));
            $taxes = $this->distributeCents(
                (int) round((float) ($parent['tax_amount'] ?? 0) * 100),
                array_map(fn (int $subtotal): float => max(0, $subtotal), $subtotals),
            );
            $totals = array_map(fn (int $subtotal, int $tax): int => $subtotal + $tax, $subtotals, $taxes);
            $advances = $this->distributeCents(
                (int) round((float) ($parent['advance_amount'] ?? 0) * 100),
                array_map(fn (int $total): float => max(0, $total), $totals),
            );
            $jobIds = [];

            foreach ($tasks as $index => $task) {
                $jobOrderId = $index === 0
                    ? $parentId
                    : $this->createTaskJobOrder(
                        $parent,
                        $task,
                        $index,
                        (int) $groupId,
                        $columns,
                        $subtotals[$index],
                        $taxes[$index],
                        $totals[$index],
                        $advances[$index],
                    );

                if ($index === 0) {
                    $this->updateTaskJobOrder($parentId, $parent, $task, $index, (int) $groupId, $subtotals[$index], $taxes[$index], $totals[$index], $advances[$index]);
                }

                DB::table('legacy_job_order_items')->insertOrIgnore([
                    'legacy_job_order_group_id' => $groupId,
                    'job_order_id' => $jobOrderId,
                    'legacy_task_id' => $task->id,
                    'task_order' => $index + 1,
                    'task_cost' => $task->task_cost ?? 0,
                    'original_quantity' => $task->quantity ?? null,
                    'task_snapshot' => json_encode((array) $task, JSON_THROW_ON_ERROR),
                    'created_at' => $task->created_at ?? now(),
                    'updated_at' => now(),
                ]);
                $jobIds[] = $jobOrderId;
            }

            $this->allocatePayments($parentId, (int) $groupId, $jobIds, $totals);
        });
    }

    private function convertTasklessParent(array $parent, int $groupId): void
    {
        $status = match ($parent['status'] ?? null) {
            'completed', 'cancelled' => $parent['status'],
            default => 'draft',
        };
        $jobNumber = filled($parent['job_order_number'] ?? null)
            ? $parent['job_order_number']
            : $this->uniqueJobOrderNumber('JO-LEGACY-'.$parent['id']);

        DB::table('job_orders')->where('id', $parent['id'])->update([
            'job_order_number' => $jobNumber,
            'name' => 'Legacy order '.$jobNumber,
            'quantity' => 1,
            'size' => null,
            'paper' => null,
            'deliverables' => null,
            'instructions' => null,
            'designer_id' => null,
            'typist_id' => null,
            'proforma_id' => null,
            'proforma_task_id' => null,
            'legacy_job_order_group_id' => $groupId,
            'status' => $status,
            'updated_at' => now(),
        ]);

        DB::table('legacy_job_order_items')->insertOrIgnore([
            'legacy_job_order_group_id' => $groupId,
            'job_order_id' => $parent['id'],
            'legacy_task_id' => null,
            'task_order' => 0,
            'task_cost' => $parent['subtotal'] ?? 0,
            'original_quantity' => null,
            'task_snapshot' => null,
            'created_at' => $parent['created_at'] ?? now(),
            'updated_at' => now(),
        ]);
    }

    private function createTaskJobOrder(
        array $parent,
        object $task,
        int $index,
        int $groupId,
        array $columns,
        int $subtotal,
        int $tax,
        int $total,
        int $advance,
    ): int
    {
        $existing = DB::table('legacy_job_order_items')->where('legacy_task_id', $task->id)->value('job_order_id');

        if ($existing) {
            return (int) $existing;
        }

        $attributes = array_intersect_key($parent, array_flip($columns));
        unset($attributes['id']);
        $attributes = array_merge($attributes, $this->taskAttributes($parent, $task, $index, $groupId, $subtotal, $tax, $total, $advance));
        $attributes['job_order_number'] = $this->uniqueJobOrderNumber($this->taskJobOrderNumber($parent, $index + 1));
        $attributes['created_at'] = $task->created_at ?? ($parent['created_at'] ?? now());
        $attributes['updated_at'] = now();

        foreach (['output_inventory_item_id', 'design_completed_at', 'production_completed_at', 'completed_at'] as $nullable) {
            if (in_array($nullable, $columns, true)) {
                $attributes[$nullable] = null;
            }
        }

        return (int) DB::table('job_orders')->insertGetId($attributes);
    }

    private function updateTaskJobOrder(
        int $jobOrderId,
        array $parent,
        object $task,
        int $index,
        int $groupId,
        int $subtotal,
        int $tax,
        int $total,
        int $advance,
    ): void {
        DB::table('job_orders')->where('id', $jobOrderId)->update(
            $this->taskAttributes($parent, $task, $index, $groupId, $subtotal, $tax, $total, $advance) + [
                'updated_at' => now(),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function taskAttributes(
        array $parent,
        object $task,
        int $index,
        int $groupId,
        ?int $subtotal,
        ?int $tax,
        ?int $total,
        ?int $advance,
    ): array {
        $parentStatus = $parent['status'] ?? null;
        $taskStatus = $task->status ?? $parentStatus;
        $status = in_array($parentStatus, ['completed', 'cancelled'], true)
            ? $parentStatus
            : (in_array($taskStatus, ['draft', 'design', 'production', 'completed', 'cancelled'], true) ? $taskStatus : 'draft');
        $baseNumber = filled($parent['job_order_number'] ?? null)
            ? (string) $parent['job_order_number']
            : 'JO-LEGACY-'.$parent['id'];
        $quantity = (int) ($task->quantity ?? 1);

        $attributes = [
            'job_order_number' => $index === 0 ? $baseNumber : $this->taskJobOrderNumber($parent, $index + 1),
            'name' => filled($task->name ?? null) ? $task->name : 'Legacy task #'.$task->id,
            'quantity' => max(1, $quantity),
            'size' => $task->size ?? null,
            'paper' => $task->paper ?? null,
            'deliverables' => $task->deliverables ?? null,
            'instructions' => $task->instructions ?? null,
            'designer_id' => $task->designer_id ?? null,
            'typist_id' => $task->typist_id ?? null,
            'proforma_id' => null,
            'proforma_task_id' => null,
            'legacy_job_order_group_id' => $groupId,
            'status' => $status,
        ];

        if ($subtotal !== null) {
            $attributes['subtotal'] = $subtotal / 100;
            $attributes['tax_amount'] = ($tax ?? 0) / 100;
            $attributes['total'] = ($total ?? 0) / 100;
            $attributes['advance_amount'] = ($advance ?? 0) / 100;
            $attributes['advance_paid'] = (bool) ($parent['advance_paid'] ?? false);
        }

        return $attributes;
    }

    private function allocatePayments(int $parentId, int $groupId, array $jobIds, array $weights): void
    {
        $payments = DB::table('payments')->where('payable_type', self::JOB_ORDER_MODEL)
            ->where('payable_id', $parentId)->orderBy('id')->get();

        foreach ($payments as $payment) {
            $amounts = $this->distributeCents((int) round((float) $payment->amount * 100), $weights);
            $withholding = $this->distributeCents((int) round((float) ($payment->withholding_amount ?? 0) * 100), $weights);

            foreach ($jobIds as $index => $jobOrderId) {
                DB::table('legacy_job_order_payment_allocations')->insertOrIgnore([
                    'legacy_job_order_group_id' => $groupId,
                    'job_order_id' => $jobOrderId,
                    'payment_id' => $payment->id,
                    'amount' => $amounts[$index] / 100,
                    'withholding_amount' => $withholding[$index] / 100,
                    'created_at' => $payment->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function remapTaskReferences(): void
    {
        if (! Schema::hasTable('legacy_job_order_items')) {
            return;
        }

        $map = DB::table('legacy_job_order_items')->whereNotNull('legacy_task_id')
            ->pluck('job_order_id', 'legacy_task_id')->all();

        foreach (['dispatch_items', 'production_plan_items', 'material_requests', 'text_files', 'dielines'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'job_order_task_id')) {
                $this->dropForeignKeysForColumn($table, 'job_order_task_id');
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->renameColumn('job_order_task_id', 'legacy_job_order_task_id');
                });
            }

            $this->ensureLegacyTaskReferenceNullable($table);

            $nullable = $table === 'dielines';
            if (! Schema::hasColumn($table, 'job_order_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($nullable): void {
                    $column = $blueprint->unsignedBigInteger('job_order_id');
                    if ($nullable) {
                        $column->nullable();
                    }
                });
            }

            if (Schema::hasColumn($table, 'legacy_job_order_task_id')) {
                foreach ($map as $legacyTaskId => $jobOrderId) {
                    DB::table($table)->where('legacy_job_order_task_id', $legacyTaskId)
                        ->update(['job_order_id' => $jobOrderId]);
                }
            }

            $this->ensureJobOrderForeignKey($table, $nullable);
        }

        if (Schema::hasTable('artworks')) {
            if (Schema::hasColumn('artworks', 'job_order_task_id')) {
                $this->dropForeignKeysForColumn('artworks', 'job_order_task_id');
                Schema::table('artworks', function (Blueprint $blueprint): void {
                    $blueprint->renameColumn('job_order_task_id', 'legacy_job_order_task_id');
                });
            }

            $this->ensureLegacyTaskReferenceNullable('artworks');

            if (! Schema::hasColumn('artworks', 'job_order_id')) {
                Schema::table('artworks', function (Blueprint $blueprint): void {
                    $blueprint->foreignId('job_order_id')->nullable()->constrained()->nullOnDelete();
                });
            }

            if (Schema::hasColumn('artworks', 'legacy_job_order_task_id')) {
                foreach ($map as $legacyTaskId => $jobOrderId) {
                    DB::table('artworks')->where('legacy_job_order_task_id', $legacyTaskId)
                        ->update(['job_order_id' => $jobOrderId]);
                }
            }
        }

        $this->remapPolymorphicReferences('stock_movements', 'reference_type', 'reference_id');
        $this->remapPolymorphicReferences('activity_log', 'subject_type', 'subject_id');
        $this->remapPolymorphicReferences('journal_entries', 'source_type', 'source_id');
    }

    private function remapPolymorphicReferences(string $table, string $typeColumn, string $idColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $typeColumn) || ! Schema::hasColumn($table, $idColumn)) {
            return;
        }

        $map = DB::table('legacy_job_order_items')->whereNotNull('legacy_task_id')
            ->pluck('job_order_id', 'legacy_task_id')->all();

        foreach ($map as $legacyTaskId => $jobOrderId) {
            DB::table($table)->where($typeColumn, self::LEGACY_TASK_MODEL)->where($idColumn, $legacyTaskId)
                ->update([$typeColumn => self::JOB_ORDER_MODEL, $idColumn => $jobOrderId]);
        }
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'] ?? [], true)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($foreignKey['columns']));
            }
        }
    }

    private function ensureJobOrderForeignKey(string $table, bool $nullable): void
    {
        $hasForeignKey = collect(Schema::getForeignKeys($table))->contains(fn (array $foreignKey): bool =>
            in_array('job_order_id', $foreignKey['columns'] ?? [], true)
            && ($foreignKey['foreign_table'] ?? null) === 'job_orders'
        );

        if (! $hasForeignKey) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $foreign = $blueprint->foreign('job_order_id')->references('id')->on('job_orders');
                if (in_array($table, ['material_requests', 'text_files'], true)) {
                    $foreign->cascadeOnDelete();
                } elseif ($table === 'dielines') {
                    $foreign->nullOnDelete();
                } else {
                    $foreign->restrictOnDelete();
                }
            });
        }

        $jobOrderColumn = collect(Schema::getColumns($table))->firstWhere('name', 'job_order_id');
        if (! $nullable && ($jobOrderColumn['nullable'] ?? false)) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('job_order_id')->nullable(false)->change();
            });
        }
    }

    private function ensureLegacyTaskReferenceNullable(string $table): void
    {
        if (! Schema::hasColumn($table, 'legacy_job_order_task_id')) {
            return;
        }

        $column = collect(Schema::getColumns($table))->firstWhere('name', 'legacy_job_order_task_id');
        if ($column && ! ($column['nullable'] ?? false)) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('legacy_job_order_task_id')->nullable()->change();
            });
        }
    }

    private function archiveLegacyTaskTable(): void
    {
        if (! Schema::hasTable('job_order_tasks')) {
            return;
        }

        if (Schema::hasTable('legacy_job_order_tasks')) {
            if (DB::table('legacy_job_order_tasks')->exists()) {
                throw new RuntimeException('Both legacy task tables contain data. Stop the migration and preserve both tables for manual reconciliation.');
            }

            Schema::drop('legacy_job_order_tasks');
        }

        Schema::rename('job_order_tasks', 'legacy_job_order_tasks');
    }

    private function uniqueJobOrderNumber(string $number): string
    {
        $candidate = $number;
        $suffix = 1;
        while (DB::table('job_orders')->where('job_order_number', $candidate)->exists()) {
            $candidate = $number.'-'.$suffix++;
        }

        return $candidate;
    }

    private function taskJobOrderNumber(array $parent, int $sequence): string
    {
        $base = filled($parent['job_order_number'] ?? null)
            ? (string) $parent['job_order_number']
            : 'JO-LEGACY-'.$parent['id'];

        return $sequence === 1 ? $base : $base.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    /** @param array<int, float> $weights @return array<int, int> */
    private function distributeCents(int $amount, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $weights = array_map(fn (float $weight): float => max(0, $weight), $weights);
        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            $weights = array_fill(0, count($weights), 1.0);
            $weightTotal = count($weights);
        }

        $remaining = $amount;
        $allocations = [];
        foreach ($weights as $index => $weight) {
            $part = $index === array_key_last($weights)
                ? $remaining
                : (int) round($amount * ($weight / $weightTotal));
            $allocations[] = $part;
            $remaining -= $part;
        }

        return $allocations;
    }
}

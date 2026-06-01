<?php

namespace App\Services\Costing;

use App\Models\CostEstimate;
use App\Models\Proforma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CostEstimateService
{
    public function __construct(private CostingRegistry $registry) {}

    public function recalculate(CostEstimate $estimate): CostEstimate
    {
        $result = $this->registry
            ->calculator($estimate->job_type)
            ->calculate($estimate->toArray());

        DB::transaction(function () use ($estimate, $result): void {
            $estimate->updateQuietly($result->totals());

            $estimate->lines()->delete();
            foreach ($result->lines as $index => $line) {
                $estimate->lines()->create([
                    ...$line,
                    'sort' => $index + 1,
                ]);
            }

            $this->syncInputs($estimate);
        });

        return $estimate->refresh();
    }

    public function finalize(CostEstimate $estimate): CostEstimate
    {
        if ($estimate->status !== 'draft') {
            return $estimate;
        }

        $this->recalculate($estimate);

        $estimate->update([
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);

        return $estimate->refresh();
    }

    /**
     * @param  array{partner_id?: int, issue_date?: mixed, expiry_date?: mixed}  $data
     */
    public function createProforma(CostEstimate $estimate, array $data = []): Proforma
    {
        return DB::transaction(function () use ($estimate, $data): Proforma {
            $estimate = $estimate->loadMissing(['lines', 'partner']);

            if ($estimate->status === 'draft') {
                $estimate = $this->finalize($estimate);
                $estimate->loadMissing(['lines', 'partner']);
            }

            if ($estimate->proforma()->exists()) {
                return $estimate->proforma()->firstOrFail();
            }

            $partnerId = $data['partner_id'] ?? $estimate->partner_id;

            if (blank($partnerId)) {
                throw ValidationException::withMessages([
                    'partner_id' => 'Select a customer before creating a proforma.',
                ]);
            }

            $proforma = Proforma::create([
                'cost_estimate_id' => $estimate->id,
                'partner_id' => $partnerId,
                'job_type' => $estimate->job_type,
                'services' => $estimate->services,
                'issue_date' => $data['issue_date'] ?? now(),
                'expiry_date' => $data['expiry_date'] ?? $estimate->deadline ?? now()->addDays(15),
                'remarks' => $estimate->remarks,
                'subtotal' => $estimate->subtotal + $estimate->overhead_amount + $estimate->profit_amount - $estimate->discount_amount,
                'tax_amount' => $estimate->tax_amount,
                'total' => $estimate->total,
                'status' => 'draft',
            ]);

            $proforma->tasks()->create([
                'name' => $estimate->description ?: str($estimate->job_type)->headline()->value(),
                'quantity' => max(1, (int) $estimate->quantity),
                'size' => $this->sizeLabel($estimate),
                'unit_price' => $estimate->unit_price,
                'task_cost' => $estimate->total,
                'paper' => $this->paperSnapshot($estimate),
                'deliverables' => [],
                'instructions' => $estimate->remarks,
                'inputs' => $estimate->services,
                'cost_breakdown' => $estimate->lines->toArray(),
                'rate_snapshot' => $estimate->settings_snapshot,
            ]);

            $estimate->update(['status' => 'converted']);

            return $proforma;
        });
    }

    private function syncInputs(CostEstimate $estimate): void
    {
        foreach (($estimate->services ?? []) as $step => $payload) {
            $estimate->inputs()->updateOrCreate(
                ['step' => (string) $step],
                ['payload' => is_array($payload) ? $payload : ['value' => $payload]],
            );
        }
    }

    private function sizeLabel(CostEstimate $estimate): ?string
    {
        $services = $estimate->services ?? [];

        if ($estimate->job_type === 'labels') {
            return trim(($services['label']['width'] ?? '').' x '.($services['label']['height'] ?? '')) ?: null;
        }

        if ($estimate->job_type === 'packages') {
            return trim(($services['box']['length'] ?? '').' x '.($services['box']['width'] ?? '').' x '.($services['box']['height'] ?? '')) ?: null;
        }

        return null;
    }

    private function paperSnapshot(CostEstimate $estimate): array
    {
        return $estimate->lines
            ->filter(fn ($line): bool => $line->category === 'Material' && filled($line->inventory_item_id))
            ->map(fn ($line): array => [
                'inventory_item_id' => $line->inventory_item_id,
                'required_quantity' => (float) $line->quantity,
                'reserve_quantity' => 0,
                'base_unit' => $line->unit,
            ])
            ->values()
            ->all();
    }
}

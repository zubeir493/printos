<?php

namespace App\Services\Costing;

use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Setting;
use App\Services\Costing\Concerns\BuildsCostingResults;

class LabelCostCalculator implements CostingCalculator
{
    use BuildsCostingResults;

    public function calculate(array $data): CostingResult
    {
        $settings = Setting::getSettings();
        $services = $data['services'] ?? [];
        $spec = $services['label'] ?? [];
        $material = $services['material'] ?? [];
        $production = $services['production'] ?? [];
        $finishing = $services['finishing'] ?? [];
        $commercial = $services['commercial'] ?? [];
        $defaults = $settings->costing_defaults ?? [];

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $colors = max(1, (int) ($spec['colors'] ?? 1));
        $yield = max(1, (float) ($spec['yield'] ?? 1));
        $printingUp = max(1, (float) ($production['printing_up'] ?? 1));
        $diecuttingUp = max(1, (float) ($production['diecutting_up'] ?? 1));
        $wasteAllowance = max(0, (float) ($spec['waste_allowance_percent'] ?? ($defaults['waste_percent'] ?? 3)));

        $materialItem = InventoryItem::query()->find($material['material_item_id'] ?? null);
        $inkItem = InventoryItem::query()->find($material['ink_item_id'] ?? null);
        $adhesiveItem = InventoryItem::query()->find($material['adhesive_item_id'] ?? null);
        $linerItem = InventoryItem::query()->find($material['liner_item_id'] ?? null);
        $laminationItem = InventoryItem::query()->find($material['lamination_item_id'] ?? null);
        $packingItem = InventoryItem::query()->find($finishing['packing_item_id'] ?? null);
        $machine = Machine::query()->find($production['machine_id'] ?? null);

        $paperQuantity = ceil($quantity / $yield * (1 + $wasteAllowance / 100));
        $impressions = ceil($quantity * $colors / ($printingUp * 1000));
        $machineSpeed = max(1, (float) ($machine?->production_speed ?: 1000));
        $machineHours = ($quantity / $printingUp) / $machineSpeed;

        $lines = [
            $this->line('Material', 'Label stock', $paperQuantity, $materialItem?->unit, $this->itemCost($materialItem, (float) ($material['material_unit_cost'] ?? 0)), $materialItem),
            $this->line('Ink', 'Ink consumption', $colors, $inkItem?->unit ?? 'color', $this->itemCost($inkItem, (float) ($material['ink_unit_cost'] ?? 0)), $inkItem),
            $this->line('Material', 'Adhesive', $paperQuantity, $adhesiveItem?->unit ?? 'unit', $this->itemCost($adhesiveItem), $adhesiveItem),
            $this->line('Material', 'Liner', $paperQuantity, $linerItem?->unit ?? 'unit', $this->itemCost($linerItem), $linerItem),
            $this->line('Plate', 'Printing plates', $colors, 'plate', (float) ($defaults['plate_unit_cost'] ?? 1000)),
            $this->line('Machine', 'Printing', $machineHours, 'hour', (float) ($machine?->hourly_cost ?: 200), null, ['machine' => $this->machineSnapshot($machine), 'impressions' => $impressions]),
            $this->line('Labour', 'Make ready', $colors, 'color', (float) ($defaults['make_ready_unit_cost'] ?? 50), null, ['machine' => $this->machineSnapshot($machine)]),
            $this->line('Finishing', 'Diecutting', ceil($quantity / $diecuttingUp), 'pcs', (float) ($defaults['label_diecutting_unit_cost'] ?? 0)),
            $this->line('Finishing', 'Cutting', ceil($paperQuantity / 100), 'batch', (float) ($defaults['label_cutting_unit_cost'] ?? 25)),
            $this->line('Packing', 'Packing', ceil($quantity / max(1, (float) ($finishing['bundle_size'] ?? 2000))), $packingItem?->unit ?? 'bundle', $this->itemCost($packingItem), $packingItem),
            $this->line('Finishing', 'Lamination', $paperQuantity, $laminationItem?->unit ?? 'unit', $this->itemCost($laminationItem), $laminationItem),
        ];

        return $this->commercialTotals($lines, $quantity, $commercial, $settings);
    }
}

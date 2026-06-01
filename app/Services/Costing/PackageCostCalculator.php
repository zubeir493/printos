<?php

namespace App\Services\Costing;

use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Setting;
use App\Services\Costing\Concerns\BuildsCostingResults;

class PackageCostCalculator implements CostingCalculator
{
    use BuildsCostingResults;

    public function calculate(array $data): CostingResult
    {
        $settings = Setting::getSettings();
        $services = $data['services'] ?? [];
        $box = $services['box'] ?? [];
        $layout = $services['layout'] ?? [];
        $material = $services['material'] ?? [];
        $operations = $services['operations'] ?? [];
        $packing = $services['packing'] ?? [];
        $commercial = $services['commercial'] ?? [];
        $defaults = $settings->costing_defaults ?? [];

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $colors = max(1, (int) ($box['colors'] ?? 1));
        $ups = max(1, (float) ($layout['ups'] ?? 1));
        $waste = max(0, (float) ($layout['waste_percent'] ?? ($defaults['waste_percent'] ?? 3)));
        $sheetCount = ceil(($quantity / $ups) * (1 + $waste / 100));

        $boardItem = InventoryItem::query()->find($material['board_item_id'] ?? null);
        $inkItem = InventoryItem::query()->find($material['ink_item_id'] ?? null);
        $glueItem = InventoryItem::query()->find($material['glue_item_id'] ?? null);
        $laminationItem = InventoryItem::query()->find($material['lamination_item_id'] ?? null);
        $coatingItem = InventoryItem::query()->find($material['coating_item_id'] ?? null);
        $cartonItem = InventoryItem::query()->find($packing['carton_item_id'] ?? null);
        $printingMachine = Machine::query()->find($operations['printing_machine_id'] ?? null);
        $dieMachine = Machine::query()->find($operations['diecutting_machine_id'] ?? null);
        $gluerMachine = Machine::query()->find($operations['folder_gluer_machine_id'] ?? null);

        $printLength = (float) ($layout['print_length'] ?? (($box['length'] ?? 0) + ($box['width'] ?? 0) + ($box['flap_size'] ?? 0)));
        $printWidth = (float) ($layout['print_width'] ?? ((($box['length'] ?? 0) + ($box['width'] ?? 0)) * 2 + ($box['glue_area'] ?? 0)));
        $inkCoverage = max(0, (float) ($box['print_coverage_percent'] ?? 1.5));
        $inkKg = $printLength * $printWidth * 0.0001 * $quantity / $ups * $inkCoverage / 1000;
        $varnishKg = $inkKg / 2;
        $cartons = ceil($quantity / max(1, (float) ($packing['units_per_carton'] ?? 1200)));

        $printingSpeed = max(1, (float) ($printingMachine?->production_speed ?: 20000));
        $dieSpeed = max(1, (float) ($dieMachine?->production_speed ?: 20000));
        $gluerSpeed = max(1, (float) ($gluerMachine?->production_speed ?: 20000));

        $lines = [
            $this->line('Material', 'Board sheets', $sheetCount, $boardItem?->unit, $this->itemCost($boardItem, (float) ($material['board_unit_cost'] ?? 0)), $boardItem, ['sheet_utilization' => $ups]),
            $this->line('Plate', 'Printing plates', $colors, 'plate', (float) ($defaults['plate_unit_cost'] ?? 900)),
            $this->line('Ink', 'Ink', $inkKg, $inkItem?->unit ?? 'kg', $this->itemCost($inkItem, (float) ($material['ink_unit_cost'] ?? 3000)), $inkItem),
            $this->line('Finishing', 'Lamination', $sheetCount, $laminationItem?->unit ?? 'unit', $this->itemCost($laminationItem), $laminationItem),
            $this->line('Finishing', 'Coating', $sheetCount, $coatingItem?->unit ?? 'unit', $this->itemCost($coatingItem), $coatingItem),
            $this->line('Finishing', 'Varnish', $varnishKg, 'kg', (float) ($defaults['varnish_unit_cost'] ?? 0)),
            $this->line('Die', 'Die block', (float) ($operations['die_count'] ?? 1), 'set', (float) ($defaults['package_die_unit_cost'] ?? 10000)),
            $this->line('Packing', 'Cartons / bags', $cartons, $cartonItem?->unit ?? 'carton', $this->itemCost($cartonItem), $cartonItem),
            $this->line('Material', 'Glue', $quantity, $glueItem?->unit ?? 'pcs', $this->itemCost($glueItem), $glueItem),
            $this->line('Labour', 'Design', (float) ($operations['design_hours'] ?? 2), 'hour', (float) ($operations['design_hourly_cost'] ?? 600)),
            $this->line('Machine', 'Printing', $quantity / $ups * $colors / $printingSpeed, 'hour', (float) ($printingMachine?->hourly_cost ?: 250), null, ['machine' => $this->machineSnapshot($printingMachine)]),
            $this->line('Machine', 'Die cutting', $quantity / $ups / $dieSpeed, 'hour', (float) ($dieMachine?->hourly_cost ?: 175), null, ['machine' => $this->machineSnapshot($dieMachine)]),
            $this->line('Machine', 'Folder gluer', $quantity / $gluerSpeed, 'hour', (float) ($gluerMachine?->hourly_cost ?: 200), null, ['machine' => $this->machineSnapshot($gluerMachine)]),
            $this->line('Finishing', 'Manual finishing', $quantity, 'pcs', (float) ($defaults['manual_finishing_unit_cost'] ?? 0.05)),
        ];

        return $this->commercialTotals($lines, $quantity, $commercial, $settings);
    }
}

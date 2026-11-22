<?php

namespace App\Services\Costing;

use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Setting;
use Illuminate\Support\Collection;

class SheetfedCostCalculator implements CostingCalculator
{
    public function __construct(private SheetLayoutCalculator $layouts, private EstimatorRequestContext $sources) {}

    public function calculate(array $data, bool $fresh = true): CostingResult
    {
        if ($fresh) {
            $this->sources->refresh();
        }
        $settings = $this->sources->settings();
        $services = $data['services'] ?? [];
        $product = $services['product'] ?? [];
        $components = array_values(array_filter($services['components'] ?? [], 'is_array'));
        $finishing = array_values(array_filter($services['finishing'] ?? [], 'is_array'));
        foreach ($finishing as $index => &$operation) {
            $operation['sequence'] = (($operation['basis'] ?? 'products') === 'products' ? 1000 : 100) + $index;
        }
        unset($operation);
        $consumables = array_values(array_filter($services['consumables'] ?? [], 'is_array'));
        $commercial = $services['commercial'] ?? [];
        $quantity = (int) ($data['quantity'] ?? 0);
        $type = $data['job_type'] ?? '';
        $product['family'] = $type;
        $context = ['diagnostics' => [], 'warnings' => [], 'lines' => [], 'operations' => [], 'layouts' => []];
        $itemIds = collect($components)->flatMap(fn (array $row): array => [$row['material_id'] ?? null, $row['ink_item_id'] ?? null, $row['plate_item_id'] ?? null])
            ->merge(collect($consumables)->pluck('material_id'))->filter()->unique();
        $items = $this->sources->items($itemIds->all());
        $machineIds = collect($components)->flatMap(fn (array $row): array => [$row['machine_id'] ?? null, $row['precut_machine_id'] ?? null])->merge(collect($finishing)->pluck('machine_id'))->filter()->unique();
        $machines = $this->sources->machines()->only($machineIds->all());

        if ($quantity < 1 || $quantity > 100000000) {
            $context['diagnostics'][] = 'Enter a quantity between 1 and 100,000,000.';
        }
        if (! in_array($type, ['books', 'labels', 'packages', 'stickers'], true)) {
            $context['diagnostics'][] = 'Select a supported product family.';
        }
        if ($components === []) {
            $context['diagnostics'][] = 'Add the paper or board components for this product.';
        }
        $this->validateRoute($type, $product, $components, $finishing, $machines, $context);
        if (! ($product['consumables_confirmed'] ?? false)) {
            $context['diagnostics'][] = 'Review and confirm all required consumables and tooling, including glue, wire, dies, film and packing.';
        }
        $consumableCategories = $items->only(collect($consumables)->pluck('material_id')->filter()->all())->pluck('category');
        if ($type === 'books') {
            $bindingCategory = ($product['binding'] ?? 'perfect') === 'saddle' ? 'wire' : 'glue';
            if (! $consumableCategories->contains($bindingCategory)) {
                $context['diagnostics'][] = 'Add '.($bindingCategory === 'wire' ? 'binding wire' : 'binding glue').' consumption from inventory.';
            }
        }
        $selectedOperations = collect($finishing)->map(fn (array $row): ?string => $machines->get($row['machine_id'] ?? null)?->operation_type);
        foreach (['die_cutting' => 'die', 'kiss_cutting' => 'die', 'laminating' => 'lamination', 'coating' => 'coating', 'foiling' => 'foil', 'folder_gluer' => 'glue'] as $operation => $category) {
            if ($selectedOperations->contains($operation) && ! $consumableCategories->contains($category)) {
                $context['diagnostics'][] = 'Add '.str_replace('_', ' ', $category).' consumption/tooling for '.str_replace('_', ' ', $operation).'. Existing tooling can use a documented zero-cost override.';
            }
        }
        $requiredProducts = max(1, $quantity);
        foreach (array_reverse($finishing) as $index => $row) {
            if (($row['basis'] ?? 'products') === 'products') {
                $requiredProducts = $this->finishingStage($row, $requiredProducts, $machines, null, $context);
            }
        }
        foreach ($components as $component) {
            $this->component($component, $product, $requiredProducts, $type, $items, $machines, $finishing, $context);
        }
        foreach ($consumables as $row) {
            $item = $items->get($row['material_id'] ?? null);
            $coefficient = (float) ($row['quantity_per_basis'] ?? 0);
            $basis = $row['basis'] ?? 'fixed';
            $label = (string) ($row['name'] ?? $item?->name ?? 'Consumable');
            $componentRole = $row['component_role'] ?? 'all';
            $targetLayouts = collect($context['layouts'])->filter(fn (array $layout): bool => $componentRole === 'all' || $layout['role'] === $componentRole);
            $sheetCount = (float) $targetLayouts->sum('gross_press_sheets');
            $area = (float) $targetLayouts->sum('gross_sheet_area_m2');
            if ($coefficient <= 0 || ! in_array($basis, ['fixed', 'products', 'sheets', 'm2'], true)) {
                $context['diagnostics'][] = "{$label}: enter a positive consumption quantity and a valid basis.";

                continue;
            }
            $multiplier = match ($basis) {
                'products' => $requiredProducts,
                'sheets' => $sheetCount,
                'm2' => $area,
                default => 1,
            };
            if (filled($row['operation_machine_id'] ?? null) && $basis !== 'fixed') {
                $targetOperations = collect($context['operations'])->filter(fn (array $operation): bool => $operation['machine_id'] === (int) $row['operation_machine_id']
                    && ($componentRole === 'all' || ($operation['component_role'] ?? null) === $componentRole)
                    && ($operation['basis'] ?? null) === ($basis === 'products' ? 'products' : 'sheets'));
                if ($targetOperations->isEmpty()) {
                    $context['diagnostics'][] = "{$label}: the selected consumption operation must be present in the matching production route.";
                }
                $multiplier = $basis === 'm2'
                    ? (float) $targetOperations->sum(fn (array $operation): float => $operation['input_quantity'] * $operation['sheet_area_m2'])
                    : (float) $targetOperations->sum('input_quantity');
            } elseif (in_array($basis, ['sheets', 'm2'], true) && $targetLayouts->isEmpty()) {
                $context['diagnostics'][] = "{$label}: the consumption basis refers to a missing component layout.";
            }
            $this->materialLine($label, $item, $coefficient * $multiplier, $row, $context, ['consumption_basis' => $basis, 'component_role' => $componentRole, 'operation_machine_id' => $row['operation_machine_id'] ?? null]);
        }

        $context['diagnostics'] = array_values(array_unique($context['diagnostics']));
        usort($context['operations'], fn (array $left, array $right): int => ($left['sequence'] ?? 0) <=> ($right['sequence'] ?? 0));

        return $this->result($context, max(1, $quantity), $commercial, $settings);
    }

    /** @return array<int, array<string, mixed>> */
    public function suggestPresses(array $component, array $product = []): array
    {
        return $this->suggestLayoutMachines($component, $product, 'printing');
    }

    /** @return array<int, array<string, mixed>> */
    public function suggestLayoutMachines(array $component, array $product = [], string $operation = 'printing'): array
    {
        $stock = $this->sources->item($component['material_id'] ?? null);
        if (! $stock) {
            return [];
        }
        $spec = $this->componentSpec($component, $product, (int) ($component['signature_pages'] ?? 16));

        return $this->sources->machines()->filter(fn (Machine $machine): bool => $machine->operation_type === $operation && $machine->speed_unit === 'sheets_per_hour' && $machine->is_active && $machine->costing_configured && $this->materialFitsMachine($stock, $machine))
            ->map(function (Machine $machine) use ($stock, $spec): ?array {
                $layout = $this->layouts->candidates($stock->toArray(), $machine->toArray(), $spec)[0] ?? null;

                return $layout ? ['machine_id' => $machine->id, 'name' => $machine->name, 'layout' => $layout] : null;
            })->filter()->sortBy('layout.score')->values()->take(6)->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function layoutCandidates(array $component, array $product = []): array
    {
        $stock = $this->sources->item($component['material_id'] ?? null);
        $machine = $this->sources->machine($component['machine_id'] ?? null);
        if (! $stock || ! $machine) {
            return [];
        }
        $spec = $this->componentSpec($component, $product, (int) ($component['signature_pages'] ?? 16));
        $layouts = $this->layouts->candidates($stock->toArray(), $machine->toArray(), $spec);
        $imposition = $this->sources->imposition($component['dieline_imposition_id'] ?? null);

        return $imposition ? array_values(array_filter($layouts, fn (array $layout): bool => $layout['sheet_width_mm'] === (float) $imposition->paper_width && $layout['sheet_height_mm'] === (float) $imposition->paper_height)) : $layouts;
    }

    private function component(array $component, array $product, int $products, string $type, Collection $items, Collection $machines, array $finishing, array &$context): void
    {
        $role = (string) ($component['role'] ?? 'stock');
        $label = (string) ($component['name'] ?? str($role)->headline()->value());
        $stock = $items->get($component['material_id'] ?? null);
        $machine = $machines->get($component['machine_id'] ?? null);
        $front = (int) ($component['front_colors'] ?? 0);
        $back = (int) ($component['back_colors'] ?? 0);
        $printed = $front + $back > 0;
        if ($front < 0 || $back < 0 || max($front, $back) > 12) {
            $context['diagnostics'][] = "{$label}: colors per side must be between 0 and 12.";

            return;
        }
        if (! $stock || $stock->type !== 'raw_material' || ! in_array($stock->category, ['paper', 'board', 'liner'], true)) {
            $context['diagnostics'][] = "{$label}: select paper or board from inventory.";

            return;
        }
        $stockUnit = strtolower($stock->unit);
        if (! in_array($stockUnit, ['sheet', 'sheets', 'kg', 'kilogram', 'kilograms'], true)) {
            $context['diagnostics'][] = "{$label}: sheet stock must use sheets or kilograms as its base unit.";

            return;
        }
        if ($printed && ($stock->printable_sides === null || (int) $stock->printable_sides < ($back > 0 ? 2 : 1))) {
            $context['diagnostics'][] = "{$label}: confirm the stock's printable sides.";
        }
        if ($type === 'stickers' && $role === 'stock' && ! $stock->is_self_adhesive) {
            $context['diagnostics'][] = "{$label}: select a self-adhesive stock; its price must include face stock, adhesive and liner.";
        }
        if (! $this->machineReady($machine, $printed ? 'printing' : 'cutting', $component, $context, $label)) {
            return;
        }
        if (! $this->materialFitsMachine($stock, $machine)) {
            $context['diagnostics'][] = "{$label}: stock GSM or thickness is outside the machine's configured limits.";
        }
        $grainAxis = $component['grain_axis'] ?? ($type === 'books' ? 'height' : 'any');
        if ($grainAxis !== 'any' && ! in_array($stock->grain_direction, ['long', 'short', 'none'], true)) {
            $context['diagnostics'][] = "{$label}: set the stock grain direction before confirming a grain-sensitive layout.";
        }
        if (! ($component['layout_confirmed'] ?? false)) {
            $context['diagnostics'][] = "{$label}: review and confirm the sheet layout and finishing allowances.";
        }
        $groups = [['pages' => 0, 'forms' => 1]];
        if ($role === 'text' && $type === 'books') {
            $pages = (int) ($product['page_count'] ?? 0);
            $signature = (int) ($component['signature_pages'] ?? 16);
            if ($pages < 4 || $pages > 10000 || $pages % 4 !== 0 || ! in_array($signature, [4, 8, 16, 32], true)) {
                $context['diagnostics'][] = "{$label}: text pages must be a multiple of four; choose 4, 8, 16 or 32 pages per signature.";

                return;
            }
            $groups = $this->signatures($pages, $signature);
        }
        foreach ($groups as $group) {
            $spec = $this->componentSpec($component, $product, $group['pages']);
            $candidates = $this->layouts->candidates($stock->toArray(), $machine->toArray(), $spec);
            if (filled($component['dieline_imposition_id'] ?? null)) {
                $imposition = $this->sources->imposition($component['dieline_imposition_id']);
                if ($imposition) {
                    $candidates = array_values(array_filter($candidates, fn (array $candidate): bool => $candidate['sheet_width_mm'] === (float) $imposition->paper_width && $candidate['sheet_height_mm'] === (float) $imposition->paper_height));
                }
            }
            $layoutKey = $component['layout_key'] ?? null;
            $layout = filled($layoutKey) ? collect($candidates)->firstWhere('key', $layoutKey) : ($candidates[0] ?? null);
            if (! $layout) {
                $context['diagnostics'][] = "{$label}: no valid layout fits this stock, grain direction and machine. Check dimensions or select another machine.";

                continue;
            }
            if (filled($component['dieline_imposition_id'] ?? null)) {
                $layout = $this->importImposition($component, $layout, $machine, $context, $label);
            }
            if ($type === 'packages' && empty($component['dieline_imposition_id']) && ! ($component['blank_dimensions_confirmed'] ?? false)) {
                $context['diagnostics'][] = "{$label}: enter and confirm the measured flat blank including flaps and glue tabs, or choose a completed dieline layout.";
            }
            $forms = $group['forms'];
            $piecesPerProduct = (float) ($component['units_per_product'] ?? 1);
            if ($piecesPerProduct <= 0 || $piecesPerProduct > 1000) {
                $context['diagnostics'][] = "{$label}: pieces per product must be greater than zero and no more than 1,000.";

                continue;
            }
            $neededSheets = (int) ceil($products * $piecesPerProduct / $layout['ups']) * $forms;
            $netSheets = $neededSheets;
            foreach (array_reverse($finishing) as $row) {
                if (($row['basis'] ?? 'products') === 'sheets' && ($row['component_role'] ?? 'stock') === $role) {
                    $neededSheets = $this->finishingStage($row, $neededSheets, $machines, $layout, $context);
                }
            }
            $press = $printed
                ? $this->pressRun((int) ceil($neededSheets / $forms), $front, $back, $machine, $forms, $component, $context, $label)
                : ['sheets' => $neededSheets, 'impressions' => 0, 'ink_color_sheets' => 0, 'plates' => 0, 'passes' => 0];
            $materialWaste = (float) ($component['material_waste_percent'] ?? $stock->default_waste_percent ?? 0);
            if ($materialWaste < 0 || $materialWaste >= 100) {
                $context['diagnostics'][] = "{$label}: material handling waste must be between 0 and less than 100%.";
                $materialWaste = 0;
            }
            $parentSheets = (int) ceil($press['sheets'] / $layout['cuts_per_parent']);
            if ($layout['cuts_per_parent'] > 1) {
                $precut = $machines->get($component['precut_machine_id'] ?? null);
                if ($precut?->operation_type !== 'cutting') {
                    $context['diagnostics'][] = "{$label}: select a parent-sheet cutting machine for this layout.";
                } else {
                    $parentSheets = $this->finishingStage(['name' => "{$label} · parent cutting", 'machine_id' => $precut->id, 'basis' => 'sheets', 'sequence' => -1], $parentSheets, $machines, [...$layout, 'sheet_width_mm' => $layout['parent_width_mm'], 'sheet_height_mm' => $layout['parent_height_mm']], $context);
                }
            }
            $parentSheets = (int) ceil($parentSheets / (1 - $materialWaste / 100));
            $sheetWeightKg = $layout['parent_width_mm'] * $layout['parent_height_mm'] / 1000000 * (float) $stock->gsm / 1000;
            $stockQuantity = in_array($stockUnit, ['kg', 'kilogram', 'kilograms'], true) ? $parentSheets * $sheetWeightKg : $parentSheets;
            if ($stockQuantity <= 0) {
                $context['diagnostics'][] = "{$label}: stock priced by weight requires GSM and parent-sheet dimensions.";
            }
            $this->materialLine($label, $stock, $stockQuantity, $component, $context, ['parent_sheets' => $parentSheets, 'cost_basis' => 'base_unit']);
            $areaPerSheet = $layout['sheet_width_mm'] * $layout['sheet_height_mm'] / 1000000;
            $context['layouts'][] = [
                ...$layout, 'role' => $role, 'label' => $label, 'signature_pages' => $group['pages'], 'forms' => $forms,
                'net_press_sheets' => $netSheets, 'gross_press_sheets' => $press['sheets'], 'parent_sheets' => $parentSheets,
                'waste_sheets' => $press['sheets'] - $netSheets, 'gross_sheet_area_m2' => $press['sheets'] * $areaPerSheet,
                'plates' => $press['plates'], 'passes' => $press['passes'], 'machine_name' => $machine->name,
                'alternatives' => array_slice($candidates, 0, 8),
            ];
            if ($printed) {
                if ($items->get($component['plate_item_id'] ?? null)?->category !== 'plate') {
                    $context['diagnostics'][] = "{$label}: select a printing plate from the plate inventory category.";
                }
                $this->materialLine("{$label} · plates", $items->get($component['plate_item_id'] ?? null), $press['plates'], [
                    'unit_cost_override' => $component['plate_cost_override'] ?? null,
                    'override_reason' => $component['plate_override_reason'] ?? null,
                ], $context);
                $ink = $items->get($component['ink_item_id'] ?? null);
                $coverage = (float) ($component['ink_coverage_percent'] ?? 0);
                $gramsPerM2 = (float) ($component['ink_grams_per_m2'] ?? $ink?->consumption_grams_per_m2 ?? 0);
                if ($coverage <= 0 || $coverage > 100 || $gramsPerM2 <= 0 || $ink?->category !== 'ink') {
                    $context['diagnostics'][] = "{$label}: select an ink and enter coverage (0–100%) and calibrated grams/m² at full coverage.";
                } else {
                    $printedArea = $spec['width_mm'] * $spec['height_mm'] / 1000000 * $layout['ups'];
                    $inkGrams = $printedArea * $press['ink_color_sheets'] * $coverage / 100 * $gramsPerM2;
                    $inkQuantity = $this->inkBaseQuantity($inkGrams, $ink, $context, $label);
                    $this->materialLine("{$label} · ink", $ink, $inkQuantity, [], $context, ['coverage_percent' => $coverage, 'grams_per_m2' => $gramsPerM2]);
                }
            }
        }
    }

    private function componentSpec(array $component, array $product, int $signaturePages): array
    {
        $role = $component['role'] ?? 'stock';
        $width = (float) ($component['width_mm'] ?? 0);
        $height = (float) ($component['height_mm'] ?? 0);
        if ($role === 'stock' && in_array($product['family'] ?? null, ['labels', 'stickers'], true)) {
            $width = $width > 0 ? $width : (float) ($product['width_mm'] ?? 0);
            $height = $height > 0 ? $height : (float) ($product['height_mm'] ?? 0);
        }
        if ($role === 'text') {
            [$across, $down] = match ($signaturePages) {
                4 => [2, 1], 8 => [2, 2], 32 => [4, 4], default => [4, 2],
            };
            $width = (float) ($product['width_mm'] ?? 0) * $across + max(0, $across - 1) * (float) ($component['page_gap_mm'] ?? 0);
            $height = (float) ($product['height_mm'] ?? 0) * $down + max(0, $down - 1) * (float) ($component['page_gap_mm'] ?? 0);
        }
        if (filled($component['dieline_imposition_id'] ?? null)) {
            $imposition = $this->sources->imposition($component['dieline_imposition_id']);
            $width = (float) ($imposition?->source_geometry['bounds']['width'] ?? $width);
            $height = (float) ($imposition?->source_geometry['bounds']['height'] ?? $height);
        }

        return [...$component, 'width_mm' => $width, 'height_mm' => $height];
    }

    /** @return array<int, array{pages: int, forms: int}> */
    private function signatures(int $pages, int $maximum): array
    {
        $groups = [];
        foreach ([32, 16, 8, 4] as $signature) {
            if ($signature > $maximum || $pages < $signature) {
                continue;
            }
            $forms = intdiv($pages, $signature);
            $groups[] = ['pages' => $signature, 'forms' => $forms];
            $pages %= $signature;
        }

        return $groups;
    }

    private function pressRun(int $needed, int $front, int $back, Machine $machine, int $forms, array $overrides, array &$context, string $label): array
    {
        $capacity = max(1, (int) $machine->color_units);
        $perfecting = (int) $machine->perfecting_colors_per_side;
        $passes = [];
        if ($back > 0 && $perfecting > 0 && max($front, $back) <= $perfecting) {
            $passes[] = $front + $back;
        } else {
            foreach ([$front, $back] as $colors) {
                while ($colors > 0) {
                    $passes[] = min($colors, $capacity);
                    $colors -= $capacity;
                }
            }
        }
        $gross = $needed;
        $impressions = 0;
        $inkColorSheets = 0;
        $waste = $this->wasteRate($machine, $overrides, $context, $label);
        $setupWaste = (float) ($overrides['setup_waste_override'] ?? $machine->setup_waste);
        foreach (array_reverse($passes) as $colorsInPass) {
            $running = (int) ceil($gross / (1 - $waste / 100));
            $gross = $running + (int) ceil(max(0, $setupWaste));
            $impressions += $running;
            $inkColorSheets += $gross * $colorsInPass;
        }
        $setupMinutes = (float) ($overrides['setup_minutes_override'] ?? $machine->setup_time_minutes);
        $speed = (float) ($overrides['speed_override'] ?? $machine->production_speed);
        $rate = (float) ($overrides['hourly_rate_override'] ?? $machine->hourly_cost);
        $runHours = max($impressions / max(0.000001, $speed), (float) $machine->minimum_run_minutes / 60) * $forms;
        $setupHours = max(0, $setupMinutes) / 60 * count($passes) * $forms;
        $this->machineLine("{$label} · press", $machine, $setupHours, $runHours, $rate, $context, [
            'forms' => $forms, 'passes_per_form' => count($passes), 'impressions' => $impressions * $forms,
            'setup_waste_per_pass' => $setupWaste, 'run_waste_percent' => $waste, 'effective_speed' => $speed,
        ]);

        return ['sheets' => $gross * $forms, 'impressions' => $impressions * $forms, 'ink_color_sheets' => $inkColorSheets * $forms, 'plates' => ($front + $back) * $forms, 'passes' => count($passes)];
    }

    private function finishingStage(array $row, int $output, Collection $machines, ?array $layout, array &$context): int
    {
        $machine = $machines->get($row['machine_id'] ?? null);
        $label = (string) ($row['name'] ?? $machine?->name ?? 'Finishing operation');
        if (! $this->machineReady($machine, null, $row, $context, $label)) {
            return $output;
        }
        if ($machine->operation_type === 'printing') {
            $context['diagnostics'][] = "{$label}: choose a finishing machine rather than a printing press.";
        }
        $basis = $row['basis'] ?? 'products';
        $validUnits = $basis === 'products' ? ['products_per_hour'] : ['sheets_per_hour', 'm2_per_hour'];
        if (! in_array($machine->speed_unit, $validUnits, true)) {
            $context['diagnostics'][] = "{$label}: machine speed unit does not match the operation basis.";
        }
        if ($layout && ((float) $machine->max_sheet_width_mm <= 0 || (float) $machine->max_sheet_height_mm <= 0)) {
            $context['diagnostics'][] = "{$label}: configure the finishing machine's maximum sheet dimensions.";
        }
        if ($layout && filled($machine->max_sheet_width_mm) && ! $this->layouts->fitsMachine($layout['sheet_width_mm'], $layout['sheet_height_mm'], $machine->toArray())
            && ! $this->layouts->fitsMachine($layout['sheet_height_mm'], $layout['sheet_width_mm'], $machine->toArray())) {
            $context['diagnostics'][] = "{$label}: the press sheet does not fit this finishing machine.";
        }
        $waste = $this->wasteRate($machine, $row, $context, $label);
        $running = (int) ceil($output / (1 - $waste / 100));
        $setupWaste = max(0, (float) ($row['setup_waste_override'] ?? $machine->setup_waste));
        $speed = (float) ($row['speed_override'] ?? $machine->production_speed);
        $rate = (float) ($row['hourly_rate_override'] ?? $machine->hourly_cost);
        $runQuantity = $running;
        if ($machine->speed_unit === 'm2_per_hour' && $layout) {
            $runQuantity *= $layout['sheet_width_mm'] * $layout['sheet_height_mm'] / 1000000;
        }
        $setupHours = max(0, (float) ($row['setup_minutes_override'] ?? $machine->setup_time_minutes)) / 60;
        $runHours = max($runQuantity / max(0.000001, $speed), (float) $machine->minimum_run_minutes / 60);
        $this->machineLine($label, $machine, $setupHours, $runHours, $rate, $context, ['basis' => $basis, 'component_role' => $row['component_role'] ?? null, 'output_quantity' => $output, 'input_quantity' => $running + (int) ceil($setupWaste), 'sheet_area_m2' => $layout ? $layout['sheet_width_mm'] * $layout['sheet_height_mm'] / 1000000 : 0, 'effective_speed' => $speed, 'override_reason' => $row['machine_override_reason'] ?? null, 'run_waste_percent' => $waste, 'sequence' => $row['sequence'] ?? 100]);

        return $running + (int) ceil($setupWaste);
    }

    private function machineReady(?Machine $machine, ?string $operation, array $overrides, array &$context, string $label): bool
    {
        if (! $machine || ! $machine->is_active) {
            $context['diagnostics'][] = "{$label}: select an active machine.";

            return false;
        }
        if ($operation && $machine->operation_type !== $operation) {
            $context['diagnostics'][] = "{$label}: select a machine configured for ".str_replace('_', ' ', $operation).'.';

            return false;
        }
        $hasOverride = collect(['hourly_rate_override', 'speed_override', 'setup_minutes_override', 'setup_waste_override', 'waste_percent_override'])->contains(fn (string $key): bool => isset($overrides[$key]) && $overrides[$key] !== '');
        if ($hasOverride && blank($overrides['machine_override_reason'] ?? null)) {
            $context['diagnostics'][] = "{$label}: give a reason for estimate-only machine overrides.";
        }
        if ((float) ($overrides['setup_minutes_override'] ?? $machine->setup_time_minutes) < 0 || (float) ($overrides['setup_waste_override'] ?? $machine->setup_waste) < 0) {
            $context['diagnostics'][] = "{$label}: setup time and waste cannot be negative.";
        }
        if ((int) $machine->perfecting_colors_per_side * 2 > (int) $machine->color_units) {
            $context['diagnostics'][] = "{$label}: perfecting capacity exceeds the press's available printing units.";
        }
        if (! $machine->costing_configured) {
            $context['diagnostics'][] = "{$label}: review and confirm the machine's costing configuration.";
        }
        if ((float) ($overrides['hourly_rate_override'] ?? $machine->hourly_cost) <= 0 || (float) ($overrides['speed_override'] ?? $machine->production_speed) <= 0) {
            $context['diagnostics'][] = "{$label}: enter a positive hourly rate and effective production speed.";

            return false;
        }
        if ($operation === 'printing' && ($machine->speed_unit !== 'sheets_per_hour' || (int) $machine->color_units < 1)) {
            $context['diagnostics'][] = "{$label}: set press color units and speed in sheets per hour.";

            return false;
        }

        return true;
    }

    private function materialFitsMachine(InventoryItem $stock, Machine $machine): bool
    {
        foreach ([['gsm', 'min_gsm', 'max_gsm'], ['thickness', 'min_thickness_mm', 'max_thickness_mm']] as [$attribute, $minimum, $maximum]) {
            if ((filled($machine->{$minimum}) || filled($machine->{$maximum})) && (float) $stock->{$attribute} <= 0) {
                return false;
            }
            if ((filled($machine->{$minimum}) && (float) $stock->{$attribute} < (float) $machine->{$minimum})
                || (filled($machine->{$maximum}) && (float) $stock->{$attribute} > (float) $machine->{$maximum})) {
                return false;
            }
        }

        return true;
    }

    private function wasteRate(Machine $machine, array $overrides, array &$context, string $label): float
    {
        $waste = (float) ($overrides['waste_percent_override'] ?? $machine->run_waste_percent);
        if ($waste < 0 || $waste >= 100) {
            $context['diagnostics'][] = "{$label}: running waste must be at least 0% and less than 100%.";

            return 0;
        }

        return $waste;
    }

    private function materialLine(string $label, ?InventoryItem $item, float $quantity, array $spec, array &$context, array $snapshot = []): void
    {
        if (! $item || $item->type !== 'raw_material') {
            $context['diagnostics'][] = "{$label}: select its inventory material.";

            return;
        }
        $cost = (float) $item->average_cost;
        $source = 'weighted_average';
        if (isset($spec['unit_cost_override']) && $spec['unit_cost_override'] !== '') {
            $cost = (float) $spec['unit_cost_override'];
            $source = 'estimate_override';
            if ($cost < 0 || blank($spec['override_reason'] ?? null)) {
                $context['diagnostics'][] = "{$label}: cost overrides require a nonnegative base-unit price and a reason.";
            }
        } elseif ($cost <= 0) {
            $context['diagnostics'][] = "{$label}: inventory average cost is missing. Enter an explicit base-unit cost override or receive stock at its actual cost.";
        }
        $quantity = round($quantity, 6);
        $context['lines'][] = [
            'category' => $item->category === 'die' ? 'Tooling' : 'Material', 'label' => $label, 'inventory_item_id' => $item->id, 'machine_id' => null,
            'quantity' => $quantity, 'unit' => $item->unit, 'unit_cost' => max(0, $cost), 'total' => EstimateArithmetic::amount($quantity, max(0, $cost)),
            'snapshot' => ['inventory' => $item->toArray(), 'cost_source' => $source, 'override_reason' => $spec['override_reason'] ?? null, ...$snapshot],
        ];
    }

    private function machineLine(string $label, Machine $machine, float $setup, float $running, float $rate, array &$context, array $snapshot): void
    {
        $hours = round($setup + $running, 6);
        $context['lines'][] = [
            'category' => 'Machine', 'label' => $label, 'inventory_item_id' => null, 'machine_id' => $machine->id,
            'quantity' => $hours, 'unit' => 'hour', 'unit_cost' => $rate, 'total' => EstimateArithmetic::amount($hours, $rate),
            'snapshot' => ['machine' => $machine->toArray(), 'setup_hours' => round($setup, 6), 'run_hours' => round($running, 6), 'rate_includes_shop_overhead' => true, ...$snapshot],
        ];
        $context['operations'][] = ['label' => $label, 'machine_id' => $machine->id, 'machine_name' => $machine->name, 'setup_hours' => round($setup, 6), 'run_hours' => round($running, 6), 'hourly_rate' => $rate, 'total' => EstimateArithmetic::amount($hours, $rate), ...$snapshot];
    }

    private function inkBaseQuantity(float $grams, InventoryItem $ink, array &$context, string $label): float
    {
        $unit = strtolower($ink->unit);
        if (in_array($unit, ['kg', 'kilogram', 'kilograms'], true)) {
            return $grams / 1000;
        }
        if (in_array($unit, ['g', 'gram', 'grams'], true)) {
            return $grams;
        }
        if (in_array($unit, ['l', 'litre', 'liter', 'litres', 'liters', 'ml'], true) && (float) $ink->density_kg_per_litre > 0) {
            return $grams / 1000 / (float) $ink->density_kg_per_litre * ($unit === 'ml' ? 1000 : 1);
        }
        $context['diagnostics'][] = "{$label}: ink must use grams/kilograms, or litres/millilitres with its actual density configured.";

        return 0;
    }

    private function validateRoute(string $type, array $product, array $components, array $finishing, Collection $machines, array &$context): void
    {
        $roles = collect($components)->pluck('role');
        if ($roles->unique()->count() !== $roles->count()) {
            $context['diagnostics'][] = 'Each component role must be unique.';
        }
        $requiredRoles = $type === 'books'
            ? (($product['binding'] ?? 'perfect') === 'hardcover' ? ['text', 'case', 'board', 'endsheet'] : ['text', 'cover'])
            : ['stock'];
        foreach ($components as $component) {
            $role = $component['role'] ?? 'stock';
            if (($component['product_review_confirmed'] ?? true) === false) {
                $context['diagnostics'][] = 'Review the retained '.$role.' component from the previous product. Remove it or approve keeping it in component options.';
            }
        }
        foreach ($requiredRoles as $role) {
            if (! $roles->contains($role)) {
                $context['diagnostics'][] = 'Add the '.str_replace('_', ' ', $role).' component.';
            }
        }
        $requiredOperations = match ($type) {
            'books' => match ($product['binding'] ?? 'perfect') {
                'saddle' => ['folding', 'collating', 'stitching', 'cutting'],
                'hardcover' => ['folding', 'collating', 'case_making', 'hard_cover_gluing', 'cutting'],
                default => ['folding', 'collating', 'perfect_binding', 'cutting'],
            },
            'packages' => ($product['requires_gluing'] ?? true) ? ['die_cutting', 'folder_gluer'] : ['die_cutting'],
            'stickers' => ['kiss_cutting'],
            default => [($product['shape'] ?? 'rectangle') === 'rectangle' ? 'cutting' : 'die_cutting'],
        };
        $selected = collect($finishing)->map(fn (array $row): ?string => $machines->get($row['machine_id'] ?? null)?->operation_type);
        foreach ($finishing as $row) {
            $operation = $machines->get($row['machine_id'] ?? null)?->operation_type ?? ($row['name'] ?? 'finishing');
            if (($row['product_review_confirmed'] ?? true) === false) {
                $context['diagnostics'][] = 'Review the retained '.str_replace('_', ' ', $operation).' operation from the previous binding. Remove it or approve keeping it in production route options.';
            }
        }
        foreach ($requiredOperations as $operation) {
            if (! $selected->contains($operation)) {
                $context['diagnostics'][] = 'Add a '.str_replace('_', ' ', $operation).' machine to the production route.';
            }
        }
        foreach ($finishing as $row) {
            if (($row['basis'] ?? 'products') === 'sheets' && ! $roles->contains($row['component_role'] ?? 'stock')) {
                $context['diagnostics'][] = 'A sheet-based finishing operation refers to a missing component.';
            }
        }
        $hasAssembly = false;
        foreach ($finishing as $row) {
            $basis = $row['basis'] ?? 'products';
            if ($basis === 'products') {
                $hasAssembly = true;
            } elseif ($basis !== 'sheets' || $hasAssembly) {
                $context['diagnostics'][] = 'Place all sheet-based finishing operations before finished-piece operations.';
            }
            $operation = $machines->get($row['machine_id'] ?? null)?->operation_type;
            if (in_array($operation, ['folding', 'die_cutting', 'kiss_cutting', 'laminating', 'coating', 'foiling', 'embossing'], true) && $basis !== 'sheets') {
                $context['diagnostics'][] = str_replace('_', ' ', (string) $operation).' must use press sheets as its quantity basis.';
            }
        }
        if ($type === 'books') {
            if (! in_array($product['binding'] ?? 'perfect', ['saddle', 'perfect', 'hardcover'], true)) {
                $context['diagnostics'][] = 'Select saddle stitch, perfect binding or hardcover.';
            }
            $context['warnings'][] = 'Confirm binding glue, wire/thread, lamination, packing and other consumables in the consumables section where applicable.';
        }
    }

    private function importImposition(array $component, array $layout, Machine $machine, array &$context, string $label): array
    {
        $imposition = $this->sources->imposition($component['dieline_imposition_id']);
        if (! $imposition || $imposition->status !== 'completed' || (int) $imposition->copies < 1
            || (float) $imposition->paper_width !== $layout['sheet_width_mm'] || (float) $imposition->paper_height !== $layout['sheet_height_mm']
            || (float) $imposition->margin < max((float) $machine->gripper_margin_mm, (float) $machine->side_margin_mm, (float) $machine->trailing_margin_mm)
            || count($imposition->placements ?? []) !== (int) $imposition->copies) {
            $context['diagnostics'][] = "{$label}: the completed dieline layout must match the selected press sheet and clear all machine margins.";

            return $layout;
        }

        return [...$layout, 'ups' => (int) $imposition->copies, 'utilization_percent' => (float) $imposition->utilisation, 'imposition_id' => $imposition->id, 'placements' => $imposition->placements, 'source_geometry' => $imposition->source_geometry];
    }

    private function result(array $context, int $quantity, array $commercial, Setting $settings): CostingResult
    {
        $subtotal = EstimateArithmetic::sum(array_column($context['lines'], 'total'));
        $overheadPercent = (float) ($commercial['overhead_percent'] ?? 0);
        $overheadFixed = (float) ($commercial['overhead_fixed'] ?? 0);
        $profitPercent = (float) ($commercial['profit_percent'] ?? 20);
        $discountPercent = (float) ($commercial['discount_percent'] ?? 0);
        $mode = $commercial['pricing_mode'] ?? 'margin';
        if ($overheadPercent < 0 || $overheadFixed < 0 || $profitPercent < 0 || ($mode === 'margin' && $profitPercent >= 100)
            || ! in_array($mode, ['margin', 'markup'], true) || $discountPercent < 0 || $discountPercent >= 100) {
            $context['diagnostics'][] = 'Review commercial inputs: margin and discount must be below 100%; costs and percentages cannot be negative.';
            $profitPercent = 0;
            $discountPercent = 0;
            $mode = 'margin';
        }
        $overhead = EstimateArithmetic::sum([max(0, $overheadFixed), EstimateArithmetic::amount($subtotal, max(0, $overheadPercent) / 100)]);
        $fullCost = EstimateArithmetic::sum([$subtotal, $overhead]);
        $sell = EstimateArithmetic::sellingPrice($fullCost, $profitPercent, $mode);
        $discount = EstimateArithmetic::amount($sell, $discountPercent / 100);
        $netSell = EstimateArithmetic::sum([$sell, -$discount]);
        $vatRate = $settings->vat_enabled ? (float) $settings->vat_rate : 0;
        $tax = EstimateArithmetic::amount($netSell, $vatRate / 100);
        $total = EstimateArithmetic::sum([$netSell, $tax]);
        $complete = $context['diagnostics'] === [];
        $materials = collect($context['lines'])->where('category', 'Material')->whereNotNull('inventory_item_id')->groupBy('inventory_item_id')->map(function (Collection $lines): array {
            $first = $lines->first();
            $required = round((float) $lines->sum('quantity'), 6);
            $available = (float) ($first['snapshot']['inventory']['inventory_balances_sum_quantity_on_hand'] ?? 0);

            return ['inventory_item_id' => $first['inventory_item_id'], 'name' => $first['snapshot']['inventory']['name'], 'quantity' => $required, 'unit' => $first['unit'], 'available' => $available, 'shortage' => max(0, $required - $available)];
        })->values()->all();
        $machines = collect($context['operations'])->groupBy('machine_id')->map(fn (Collection $rows): array => ['machine_id' => $rows->first()['machine_id'], 'name' => $rows->first()['machine_name'], 'quantity' => round((float) $rows->sum('setup_hours') + (float) $rows->sum('run_hours'), 6), 'unit' => 'hour'])->values()->all();

        return new CostingResult(
            subtotal: $subtotal, overheadAmount: $overhead, profitAmount: $complete ? EstimateArithmetic::sum([$sell, -$fullCost]) : 0,
            discountAmount: $complete ? $discount : 0, vatRate: $vatRate, taxAmount: $complete ? $tax : 0,
            total: $complete ? $total : 0, unitPrice: $complete ? round($netSell / $quantity, 6) : 0, marginPercent: $complete && $netSell > 0 ? round(($netSell - $fullCost) / $netSell * 100, 4) : 0,
            lines: $context['lines'], materialConsumption: $materials, machineUsage: $machines,
            settingsSnapshot: ['vat_enabled' => $settings->vat_enabled, 'vat_rate' => $vatRate, 'currency_code' => $settings->currency_code, 'machine_rate_includes_shop_overhead' => true, 'material_cost_basis' => 'weighted_average', 'rounding' => 'half_up_per_line_2dp'],
            formulaVersion: 'sheetfed-v2', diagnostics: array_values(array_unique($context['diagnostics'])), layouts: $context['layouts'], operations: $context['operations'],
            commercial: ['pricing_mode' => $mode, 'target_percent' => $profitPercent, 'production_cost' => $fullCost, 'sell_before_discount' => $sell, 'net_sell' => $complete ? $netSell : 0, 'net_profit' => $complete ? EstimateArithmetic::sum([$netSell, -$fullCost]) : 0, 'warnings' => $context['warnings']],
        );
    }
}

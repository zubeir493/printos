<?php

namespace Database\Seeders;

use App\Models\CostEstimate;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Costing\CostEstimateService;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CostingDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $warehouse = Warehouse::firstOrCreate(['code' => 'DEMO-COST'], ['name' => '[Demo] Estimator materials', 'location' => 'Sample data for testing only', 'is_default' => false]);
            $items = $this->materials($warehouse);
            $machines = $this->machines();
            $this->estimates($items, $machines);
        });
        $this->command?->info('Estimator demo data is ready. All DEMO-COST rates and stock are illustrative, not verified shop costs.');
    }

    /** @return array<string, InventoryItem> */
    private function materials(Warehouse $warehouse): array
    {
        $paper = ['unit' => 'sheet', 'width' => 70, 'height' => 100, 'grain_direction' => 'long', 'printable_sides' => 2, 'purchase_unit' => 'ream', 'conversion_factor' => 500];
        $definitions = [
            'text' => [...$paper, 'name' => '80gsm offset text paper', 'category' => 'paper', 'gsm' => 80, 'thickness' => 0.10, 'average_cost' => 8],
            'cover' => [...$paper, 'name' => '250gsm coated cover paper', 'category' => 'paper', 'gsm' => 250, 'thickness' => 0.25, 'average_cost' => 22],
            'sticker' => [...$paper, 'name' => 'Self-adhesive label sheet with liner', 'category' => 'paper', 'gsm' => 160, 'thickness' => 0.18, 'is_self_adhesive' => true, 'printable_sides' => 1, 'average_cost' => 30],
            'carton' => [...$paper, 'name' => '350gsm folding carton board', 'category' => 'board', 'gsm' => 350, 'thickness' => 0.45, 'average_cost' => 32],
            'greyboard' => [...$paper, 'name' => '1.8mm hardcover greyboard', 'category' => 'board', 'gsm' => 1100, 'thickness' => 1.8, 'printable_sides' => 0, 'average_cost' => 65],
            'ink' => ['name' => 'Offset process ink', 'category' => 'ink', 'unit' => 'kg', 'average_cost' => 1200, 'density_kg_per_litre' => 1.1, 'consumption_grams_per_m2' => 2],
            'adhesive' => ['name' => 'Label adhesive', 'category' => 'adhesive', 'unit' => 'kg', 'average_cost' => 300, 'density_kg_per_litre' => 1.05],
            'liner' => [...$paper, 'name' => 'Release liner sheet', 'category' => 'liner', 'gsm' => 80, 'thickness' => 0.1, 'printable_sides' => 0, 'average_cost' => 6],
            'film' => ['name' => 'Matt lamination film', 'category' => 'lamination', 'unit' => 'm2', 'average_cost' => 18],
            'coating' => ['name' => 'Water-based varnish', 'category' => 'coating', 'unit' => 'kg', 'average_cost' => 450, 'density_kg_per_litre' => 1, 'consumption_grams_per_m2' => 4],
            'glue' => ['name' => 'Binding and carton glue', 'category' => 'glue', 'unit' => 'kg', 'average_cost' => 350, 'density_kg_per_litre' => 1.1],
            'packing' => ['name' => 'Packing carton', 'category' => 'packing', 'unit' => 'piece', 'average_cost' => 40],
            'plate' => ['name' => 'Offset aluminium plate', 'category' => 'plate', 'unit' => 'piece', 'average_cost' => 250],
            'die' => ['name' => 'Cutting / kiss-cut die', 'category' => 'die', 'unit' => 'piece', 'average_cost' => 800],
            'wire' => ['name' => 'Stitching wire', 'category' => 'wire', 'unit' => 'kg', 'average_cost' => 500],
            'foil' => ['name' => 'Hot stamping foil', 'category' => 'foil', 'unit' => 'm2', 'average_cost' => 35],
            'other' => ['name' => 'Press cleaning solution', 'category' => 'other', 'unit' => 'l', 'average_cost' => 180],
        ];
        $items = [];
        foreach ($definitions as $key => $definition) {
            $cost = $definition['average_cost'];
            $item = InventoryItem::firstOrCreate(['sku' => 'DEMO-COST-'.strtoupper($key)], [
                'type' => 'raw_material', 'is_sellable' => false, 'default_waste_percent' => 0.5,
                ...$definition, 'name' => '[Demo] '.$definition['name'],
                'price' => $cost * ($definition['conversion_factor'] ?? 1),
            ]);
            $items[$key] = $item;
            $exists = StockMovement::query()->where('reference_type', self::class)->where('reference_id', $item->id)->where('warehouse_id', $warehouse->id)->exists();
            if (! $exists) {
                $quantity = match ($item->unit) {
                    'sheet' => 20000, 'm2' => 5000, 'piece' => 1000, default => 500
                };
                app(InventoryService::class)->createMovement($item, $warehouse, 'adjustment', $quantity, (float) $item->average_cost, self::class, $item->id);
            }
        }

        return $items;
    }

    /** @return array<string, Machine> */
    private function machines(): array
    {
        $machines = [];
        $sheetOperations = ['printing', 'folding', 'die_cutting', 'kiss_cutting', 'foiling', 'embossing', 'coating', 'cutting', 'slitting'];
        foreach (Machine::OPERATION_TYPES as $operation => $label) {
            $unit = $operation === 'laminating' ? 'm2_per_hour' : (in_array($operation, $sheetOperations, true) ? 'sheets_per_hour' : 'products_per_hour');
            $machines[$operation] = Machine::firstOrCreate(['code' => 'DEMO-COST-'.strtoupper($operation)], [
                'name' => '[Demo] '.$label, 'operation_type' => $operation, 'hourly_cost' => $operation === 'printing' ? 450 : 180,
                'production_speed' => $operation === 'printing' ? 3000 : ($operation === 'laminating' ? 500 : 1200),
                'speed_unit' => $unit, 'setup_time_minutes' => 15, 'setup_waste' => in_array($operation, $sheetOperations, true) ? 20 : 2,
                'run_waste_percent' => 1, 'minimum_run_minutes' => 0,
                'is_active' => true, 'costing_configured' => true,
                'color_units' => $operation === 'printing' ? 4 : null, 'perfecting_colors_per_side' => 0,
                'min_sheet_width_mm' => 150, 'min_sheet_height_mm' => 150, 'max_sheet_width_mm' => 700, 'max_sheet_height_mm' => 1000,
                'max_print_width_mm' => 690, 'max_print_height_mm' => 980, 'side_margin_mm' => 5, 'gripper_margin_mm' => 15, 'trailing_margin_mm' => 5,
                'min_gsm' => 40, 'max_gsm' => $operation === 'printing' ? 450 : 2000,
            ]);
        }
        $machines['book_trimming'] = Machine::firstOrCreate(['code' => 'DEMO-COST-BOOK-TRIM'], [
            'name' => '[Demo] Three-knife book trimmer', 'operation_type' => 'cutting',
            'hourly_cost' => 180, 'production_speed' => 1000, 'speed_unit' => 'products_per_hour',
            'setup_time_minutes' => 10, 'setup_waste' => 2, 'run_waste_percent' => 0.5,
            'is_active' => true, 'costing_configured' => true, 'max_sheet_width_mm' => 700, 'max_sheet_height_mm' => 1000,
        ]);

        return $machines;
    }

    /** @param array<string, InventoryItem> $items
     * @param  array<string, Machine>  $machines
     */
    private function estimates(array $items, array $machines): void
    {
        $component = fn (string $role, string $material, float $width, float $height, int $front = 4, int $back = 0): array => [
            'role' => $role, 'name' => str($role)->headline()->value(), 'material_id' => $items[$material]->id,
            'machine_id' => $machines[$front + $back > 0 ? 'printing' : 'cutting']->id, 'precut_machine_id' => $machines['cutting']->id,
            'width_mm' => $width, 'height_mm' => $height, 'front_colors' => $front, 'back_colors' => $back,
            'bleed_mm' => 3, 'gap_mm' => 2, 'grain_axis' => 'any', 'units_per_product' => 1, 'signature_pages' => 16,
            'plate_item_id' => $items['plate']->id, 'ink_item_id' => $items['ink']->id, 'ink_coverage_percent' => 25,
            'layout_confirmed' => true, 'blank_dimensions_confirmed' => true,
        ];
        $operation = fn (string $machine, string $basis = 'products', string $role = 'stock'): array => [
            'name' => $machines[$machine]->name, 'machine_id' => $machines[$machine]->id, 'basis' => $basis, 'component_role' => $role,
        ];
        $consumable = fn (string $item, float $quantity, string $basis = 'fixed'): array => [
            'name' => $items[$item]->name, 'material_id' => $items[$item]->id, 'quantity_per_basis' => $quantity, 'basis' => $basis, 'component_role' => 'all',
        ];
        $examples = [];
        foreach (['saddle', 'perfect', 'hardcover'] as $binding) {
            $components = [$component('text', 'text', 0, 0, 1, 1)];
            if ($binding === 'hardcover') {
                $components[] = $component('case', 'cover', 330, 250);
                $components[] = [...$component('board', 'greyboard', 155, 220, 0), 'units_per_product' => 2];
                $components[] = [...$component('endsheet', 'text', 296, 210, 0), 'units_per_product' => 2];
            } else {
                $components[] = $component('cover', 'cover', 304, 210);
            }
            $coverRole = $binding === 'hardcover' ? 'case' : 'cover';
            $route = [$operation('folding', 'sheets', 'text'), $operation('laminating', 'sheets', $coverRole), $operation('collating')];
            foreach (match ($binding) {
                'saddle' => ['stitching'], 'hardcover' => ['case_making', 'hard_cover_gluing'], default => ['perfect_binding']
            } as $step) {
                $route[] = $operation($step);
            }
            $route[] = $operation('book_trimming');
            $examples[$binding] = [
                'job_type' => 'books', 'description' => '[Demo] A5 '.$binding.' book', 'quantity' => 1000,
                'product' => ['width_mm' => 148, 'height_mm' => 210, 'page_count' => 64, 'binding' => $binding, 'consumables_confirmed' => true],
                'components' => $components, 'finishing' => $route,
                'consumables' => [
                    $consumable($binding === 'saddle' ? 'wire' : 'glue', $binding === 'saddle' ? 0.0002 : 0.004, 'products'),
                    [...$consumable('film', 1, 'm2'), 'component_role' => $coverRole, 'operation_machine_id' => $machines['laminating']->id],
                    $consumable('packing', 0.01, 'products'),
                ],
            ];
        }
        foreach (['labels', 'stickers', 'packages'] as $type) {
            $material = match ($type) {
                'stickers' => 'sticker', 'packages' => 'carton', default => 'text'
            };
            $route = [$operation(match ($type) {
                'stickers' => 'kiss_cutting', 'packages' => 'die_cutting', default => 'cutting'
            }, 'sheets')];
            $consumables = [$consumable('packing', 0.01, 'products')];
            if ($type !== 'labels') {
                $consumables[] = $consumable('die', 1);
            }
            if ($type === 'packages') {
                $route[] = $operation('folder_gluer');
                $consumables[] = $consumable('glue', 0.001, 'products');
            }
            $examples[$type] = [
                'job_type' => $type, 'description' => '[Demo] '.str($type)->headline(), 'quantity' => 2000,
                'product' => ['width_mm' => 50, 'height_mm' => 30, 'shape' => 'rectangle', 'requires_gluing' => true, 'consumables_confirmed' => true],
                'components' => [$component('stock', $material, $type === 'packages' ? 340 : 50, $type === 'packages' ? 250 : 30)],
                'finishing' => $route, 'consumables' => $consumables,
            ];
        }
        foreach ($examples as $key => $example) {
            $estimate = CostEstimate::firstOrCreate(['estimate_number' => 'DEMO-CE-'.strtoupper($key)], [
                'description' => $example['description'], 'job_type' => $example['job_type'], 'quantity' => $example['quantity'], 'status' => 'draft',
                'services' => ['costing_version' => 'sheetfed-v2', 'product' => $example['product'], 'components' => $example['components'], 'finishing' => $example['finishing'], 'consumables' => $example['consumables'], 'commercial' => ['pricing_mode' => 'margin', 'profit_percent' => 25], 'comparison_quantities' => '500,1000,2500'],
            ]);
            if ($estimate->wasRecentlyCreated) {
                app(CostEstimateService::class)->recalculate($estimate);
            }
        }
    }
}

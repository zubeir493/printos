<?php

namespace App\Services\Costing;

use App\Models\Machine;
use Illuminate\Support\Str;

class EstimatorRecipe
{
    public const array PRODUCTS = [
        'booklet' => ['name' => 'Saddle-stitched booklet', 'description' => 'Folded pages with a stitched spine', 'family' => 'books', 'binding' => 'saddle', 'icon' => 'book-open'],
        'paperback' => ['name' => 'Perfect-bound book', 'description' => 'Text pages with a glued soft cover', 'family' => 'books', 'binding' => 'perfect', 'icon' => 'book-open'],
        'hardcover' => ['name' => 'Hardcover book', 'description' => 'Case wrap, boards and endsheets', 'family' => 'books', 'binding' => 'hardcover', 'icon' => 'book-open'],
        'labels' => ['name' => 'Sheet labels', 'description' => 'Printed labels, trimmed or die cut', 'family' => 'labels', 'icon' => 'tag'],
        'stickers' => ['name' => 'Stickers', 'description' => 'Self-adhesive stock with kiss cutting', 'family' => 'stickers', 'icon' => 'squares-2x2'],
        'carton' => ['name' => 'Folding carton', 'description' => 'A dieline, board and optional gluing', 'family' => 'packages', 'icon' => 'cube'],
    ];

    public function __construct(private EstimatorRequestContext $sources, private SheetfedCostCalculator $calculator) {}

    public function productKey(array $data): string
    {
        return match ($data['job_type'] ?? 'labels') {
            'books' => match ($data['services']['product']['binding'] ?? 'perfect') {
                'saddle' => 'booklet', 'hardcover' => 'hardcover', default => 'paperback',
            },
            'packages' => 'carton', 'stickers' => 'stickers', default => 'labels',
        };
    }

    /** @return array<string, mixed> */
    public function apply(array $data, string $key, bool $productChanged = false): array
    {
        $preset = self::PRODUCTS[$key];
        $changed = $productChanged || $this->productKey($data) !== $key;
        $template = self::template($preset['family'], $preset['binding'] ?? 'perfect');
        $services = $data['services'] ?? [];
        $components = $services['components'] ?? [];
        if ($changed) {
            $requiredRoles = array_column($template['components'], 'role');
            $components = array_filter($components, fn (array $row): bool => in_array($row['role'] ?? '', $requiredRoles, true)
                || filled($row['material_id'] ?? null) || filled($row['machine_id'] ?? null)
                || filled($row['override_reason'] ?? null) || filled($row['machine_override_reason'] ?? null)
                || filled($row['width_mm'] ?? null) || filled($row['height_mm'] ?? null) || $this->hasOverrides($row));
        }
        foreach ($template['components'] as $defaults) {
            $existingKey = collect($components)->search(fn (array $row): bool => ($row['role'] ?? null) === $defaults['role']);
            if ($existingKey === false) {
                $components[(string) Str::uuid()] = $defaults;
            }
        }
        if ($changed) {
            foreach ($components as &$component) {
                $component['layout_confirmed'] = false;
                $component['layout_key'] = null;
                if (! in_array($component['role'] ?? '', array_column($template['components'], 'role'), true)) {
                    $component['product_review_confirmed'] = false;
                }
            }
            unset($component);
        }
        $services['product'] = [...($services['product'] ?? []), 'binding' => $preset['binding'] ?? 'perfect'];
        if ($changed) {
            $services['product']['consumables_confirmed'] = false;
        }
        $services['components'] = $components;
        $services['costing_version'] = 'sheetfed-v2';
        $data = [...$data, 'job_type' => $preset['family'], 'services' => $services];
        $route = $services['finishing'] ?? [];
        if ($changed) {
            $route = array_filter($route, fn (array $row): bool => filled($row['machine_id'] ?? null)
                || filled($row['machine_override_reason'] ?? null) || $this->hasOverrides($row)
                || collect($template['finishing'])->contains(fn (array $required): bool => ($required['name'] ?? '') === ($row['name'] ?? '')
                    && ($required['component_role'] ?? '') === ($row['component_role'] ?? ''))
                || ! in_array($row['name'] ?? '', ['Cutting', 'Die cutting', 'Kiss cutting', 'Folder gluer', 'Folding', 'Collating', 'Stitching', 'Case making', 'Hard cover gluing', 'Perfect binding', 'Final trimming'], true));
        }
        foreach ($template['finishing'] as $defaults) {
            $operation = $this->operationType($defaults['name']);
            $basis = $defaults['basis'];
            $existingKey = collect($route)->search(fn (array $row): bool => ($row['basis'] ?? 'products') === $basis
                && (($this->sources->machine($row['machine_id'] ?? null)?->operation_type ?? $this->operationType($row['name'] ?? '')) === $operation)
                && ($basis !== 'sheets' || ($row['component_role'] ?? 'stock') === ($defaults['component_role'] ?? 'stock')));
            if ($existingKey === false || blank($route[$existingKey]['machine_id'] ?? null)) {
                $choices = $this->sources->machines()->filter(fn (Machine $machine): bool => $machine->is_active && $machine->costing_configured
                    && $machine->operation_type === $operation && $machine->speed_unit === ($basis === 'sheets' ? 'sheets_per_hour' : 'products_per_hour'));
                $rowKey = $existingKey === false ? (string) Str::uuid() : $existingKey;
                $route[$rowKey] = [...$defaults, ...($route[$rowKey] ?? []), 'machine_id' => $choices->count() === 1 ? $choices->first()->id : null];
            }
        }
        if ($changed) {
            $bindingOperations = array_map(fn (array $row): string => $this->operationType($row['name']), $template['finishing']);
            foreach ($route as &$row) {
                $operation = $this->sources->machine($row['machine_id'] ?? null)?->operation_type ?? $this->operationType($row['name'] ?? '');
                if (in_array($operation, ['stitching', 'perfect_binding', 'case_making', 'hard_cover_gluing'], true) && ! in_array($operation, $bindingOperations, true)) {
                    $row['product_review_confirmed'] = false;
                }
            }
            unset($row);
        }
        $data['services']['finishing'] = collect($route)->sortBy(fn (array $row): int => ($row['basis'] ?? 'products') === 'sheets' ? 0 : 1)->all();
        if ($preset['family'] === 'books') {
            $data = $this->ensureConsumable($data, ($preset['binding'] ?? '') === 'saddle' ? 'wire' : 'glue', 'Binding '.(($preset['binding'] ?? '') === 'saddle' ? 'wire' : 'glue'), 'products');
        } elseif (in_array($preset['family'], ['packages', 'stickers'], true)) {
            $data = $this->ensureConsumable($data, 'die', 'Cutting die', 'fixed');
            if ($preset['family'] === 'packages') {
                $data = $this->ensureConsumable($data, 'glue', 'Carton glue', 'products');
            }
        }

        return $data;
    }

    public function addFinish(array $data, string $operation): array
    {
        $role = ($data['job_type'] ?? '') === 'books'
            ? (($data['services']['product']['binding'] ?? '') === 'hardcover' ? 'case' : 'cover') : 'stock';
        if (collect($data['services']['finishing'] ?? [])->contains(fn (array $row): bool => ($row['component_role'] ?? 'stock') === $role
            && ($this->sources->machine($row['machine_id'] ?? null)?->operation_type ?? $this->operationType($row['name'] ?? '')) === $operation)) {
            return $data;
        }
        $choices = $this->sources->machines()->filter(fn (Machine $machine): bool => $machine->is_active && $machine->costing_configured
            && $machine->operation_type === $operation && in_array($machine->speed_unit, ['sheets_per_hour', 'm2_per_hour'], true));
        $machineId = $choices->count() === 1 ? $choices->first()->id : null;
        $finish = [
            'name' => Machine::OPERATION_TYPES[$operation], 'machine_id' => $machineId,
            'basis' => 'sheets', 'component_role' => $role,
        ];
        $route = [];
        $inserted = false;
        foreach ($data['services']['finishing'] ?? [] as $key => $row) {
            if (! $inserted && (($row['basis'] ?? 'products') === 'products' || ($row['component_role'] ?? 'stock') === $role)) {
                $route[(string) Str::uuid()] = $finish;
                $inserted = true;
            }
            $route[$key] = $row;
        }
        if (! $inserted) {
            $route[(string) Str::uuid()] = $finish;
        }
        $data['services']['finishing'] = $route;
        $category = ['laminating' => 'lamination', 'foiling' => 'foil', 'coating' => 'coating'][$operation] ?? null;
        if ($category) {
            $data = $this->ensureConsumable($data, $category, Machine::OPERATION_TYPES[$operation].' material', 'm2', $role, $machineId);
        }
        foreach ($data['services']['components'] ?? [] as $key => $component) {
            $data['services']['components'][$key]['layout_confirmed'] = false;
        }
        $data['services']['product']['consumables_confirmed'] = false;

        return $data;
    }

    private function ensureConsumable(array $data, string $category, string $name, string $basis, string $role = 'all', ?int $machineId = null): array
    {
        $rows = $data['services']['consumables'] ?? [];
        $items = $this->sources->items(array_column($rows, 'material_id'));
        if (collect($rows)->contains(fn (array $row): bool => ($items->get($row['material_id'] ?? null)?->category === $category || ($row['name'] ?? '') === $name)
            && ($row['component_role'] ?? 'all') === $role && (int) ($row['operation_machine_id'] ?? 0) === (int) ($machineId ?? 0))) {
            return $data;
        }
        $choices = $this->sources->materialOptions([$category]);
        $materialId = count($choices) === 1 ? array_key_first($choices) : null;
        $item = $this->sources->item($materialId);
        $rows[(string) Str::uuid()] = [
            'name' => $name, 'material_id' => $materialId, 'basis' => $basis, 'component_role' => $role,
            'operation_machine_id' => $machineId,
            'quantity_per_basis' => $category === 'lamination' && $item && in_array(strtolower($item->unit), ['m2', 'm²', 'sqm'], true) ? 1 : null,
        ];
        $data['services']['consumables'] = $rows;

        return $data;
    }

    /** Fill unambiguous sources without replacing estimate-specific selections or overrides. */
    public function prepareComponent(array $component, array $product, string $family): array
    {
        $printed = (int) ($component['front_colors'] ?? 0) + (int) ($component['back_colors'] ?? 0) > 0;
        if (blank($component['machine_id'] ?? null)) {
            $presses = $this->calculator->suggestLayoutMachines($component, [...$product, 'family' => $family], $printed ? 'printing' : 'cutting');
            if (count($presses) === 1) {
                $component['machine_id'] = $presses[0]['machine_id'];
            }
        }
        foreach (['plate_item_id' => 'plate', 'ink_item_id' => 'ink'] as $field => $category) {
            $choices = $this->sources->materialOptions([$category]);
            if ($printed && blank($component[$field] ?? null) && count($choices) === 1) {
                $component[$field] = array_key_first($choices);
            }
        }

        return $component;
    }

    private function operationType(string $name): string
    {
        return match (strtolower($name)) {
            'final trimming' => 'cutting',
            default => str_replace(' ', '_', strtolower($name)),
        };
    }

    private function hasOverrides(array $row): bool
    {
        return collect($row)->contains(fn (mixed $value, string $key): bool => str_ends_with($key, '_override') && $value !== null && $value !== '');
    }

    /** @return array{components: list<array<string, mixed>>, finishing: list<array<string, mixed>>} */
    public static function template(string $type, string $binding = 'perfect'): array
    {
        $base = ['front_colors' => 4, 'back_colors' => 0, 'bleed_mm' => 3, 'gap_mm' => 2, 'grain_axis' => 'any', 'units_per_product' => 1, 'layout_confirmed' => false];
        if ($type !== 'books') {
            $route = match ($type) {
                'packages' => [['name' => 'Die cutting', 'basis' => 'sheets', 'component_role' => 'stock'], ['name' => 'Folder gluer', 'basis' => 'products']],
                'stickers' => [['name' => 'Kiss cutting', 'basis' => 'sheets', 'component_role' => 'stock']],
                default => [['name' => 'Cutting', 'basis' => 'sheets', 'component_role' => 'stock']],
            };

            return ['components' => [[...$base, 'role' => 'stock', 'name' => match ($type) {
                'packages' => 'Carton board', 'stickers' => 'Self-adhesive stock', default => 'Label stock',
            }]], 'finishing' => $route];
        }
        $names = ['text' => 'Book text', 'cover' => 'Soft cover', 'case' => 'Case wrap', 'board' => 'Cover boards', 'endsheet' => 'Endsheets'];
        $roles = $binding === 'hardcover' ? ['text', 'case', 'board', 'endsheet'] : ['text', 'cover'];
        $components = array_map(fn (string $role): array => [...$base, 'role' => $role, 'name' => $names[$role], 'front_colors' => in_array($role, ['board', 'endsheet'], true) ? 0 : ($role === 'text' ? 1 : 4), 'back_colors' => $role === 'text' ? 1 : 0, 'signature_pages' => 16, 'grain_axis' => 'height', 'units_per_product' => in_array($role, ['board', 'endsheet'], true) ? 2 : 1], $roles);
        $route = [['name' => 'Folding', 'basis' => 'sheets', 'component_role' => 'text'], ['name' => 'Collating', 'basis' => 'products']];
        foreach (match ($binding) {
            'saddle' => ['Stitching'], 'hardcover' => ['Case making', 'Hard cover gluing'], default => ['Perfect binding'],
        } as $operation) {
            $route[] = ['name' => $operation, 'basis' => 'products'];
        }
        $route[] = ['name' => 'Final trimming', 'basis' => 'products'];

        return ['components' => $components, 'finishing' => $route];
    }
}

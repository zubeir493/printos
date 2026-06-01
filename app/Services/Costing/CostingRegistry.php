<?php

namespace App\Services\Costing;

use App\Filament\Resources\CostEstimates\Schemas\LabelCostingWizardSchema;
use App\Filament\Resources\CostEstimates\Schemas\PackageCostingWizardSchema;
use App\Models\CostingServiceType;
use Illuminate\Support\Collection;

class CostingRegistry
{
    /**
     * @return array<string, array{name: string, calculator: class-string<CostingCalculator>, schema: class-string}>
     */
    public function fallbackTypes(): array
    {
        return [
            'labels' => [
                'name' => 'Label Printing',
                'calculator' => LabelCostCalculator::class,
                'schema' => LabelCostingWizardSchema::class,
            ],
            'packages' => [
                'name' => 'Package Printing',
                'calculator' => PackageCostCalculator::class,
                'schema' => PackageCostingWizardSchema::class,
            ],
        ];
    }

    public function calculator(string $serviceType): CostingCalculator
    {
        $definition = $this->definition($serviceType);

        return app($definition['calculator']);
    }

    public function schemaClass(string $serviceType): string
    {
        return $this->definition($serviceType)['schema'];
    }

    public function options(): array
    {
        $types = CostingServiceType::query()
            ->where('active', true)
            ->get()
            ->mapWithKeys(fn (CostingServiceType $type): array => [
                $type->key => [
                    'name' => $type->name,
                    'calculator' => $type->calculator_class,
                    'schema' => $type->schema_class,
                ],
            ]);

        return Collection::make($this->fallbackTypes())
            ->merge($types)
            ->map(fn (array $type): string => $type['name'])
            ->all();
    }

    private function definition(string $serviceType): array
    {
        $type = CostingServiceType::query()
            ->where('key', $serviceType)
            ->where('active', true)
            ->first();

        if ($type) {
            return [
                'name' => $type->name,
                'calculator' => $type->calculator_class,
                'schema' => $type->schema_class,
            ];
        }

        return $this->fallbackTypes()[$serviceType] ?? $this->fallbackTypes()['labels'];
    }
}

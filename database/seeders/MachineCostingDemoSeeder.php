<?php

namespace Database\Seeders;

use App\Models\Machine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MachineCostingDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->machines() as $machine) {
            $record = Machine::query()->firstOrCreate(
                ['code' => $machine['code']],
                ['name' => $machine['name'], ...$machine['configuration']],
            );

            if ($record->costing_configured) {
                continue;
            }

            $missingConfiguration = collect($machine['configuration'])
                ->filter(function (mixed $value, string $key) use ($record): bool {
                    $currentValue = $record->getAttribute($key);

                    return blank($currentValue) || (is_numeric($currentValue) && (float) $currentValue === 0.0);
                })
                ->all();

            $record->fill($missingConfiguration);

            if ($record->hourly_cost > 0 && $record->production_speed > 0 && filled($record->operation_type) && filled($record->speed_unit)) {
                $record->costing_configured = true;
            }

            $record->save();
        }

        $this->command?->info('Machine demo costing is ready. Speeds, setup losses and ETB hourly rates are illustrative estimating defaults.');
    }

    /**
     * @return list<array{code: string, name: string, configuration: array<string, string|int|float|bool|null>}>
     */
    private function machines(): array
    {
        return [
            ['code' => 'PRESS-SM52', 'name' => 'Heidelberg SM 52', 'configuration' => $this->configuration('printing', 850, 7000, 'sheets_per_hour', 35, 100, 1.5, 4, 0, 100, 140, 370, 520, 360, 510, 10, 40, 400, 0.03, 0.40)],
            ['code' => 'PRESS-SM74', 'name' => 'Heidelberg SM 74', 'configuration' => $this->configuration('printing', 900, 8200, 'sheets_per_hour', 40, 120, 1.5, 4, 0, 150, 210, 530, 740, 510, 720, 10, 40, 450, 0.03, 0.60)],
            ['code' => 'PRESS-KLS29', 'name' => 'Komori Lithrone S29', 'configuration' => $this->configuration('printing', 900, 8500, 'sheets_per_hour', 40, 120, 1.5, 4, 0, 200, 280, 530, 750, 520, 740, 10, 40, 450, 0.04, 0.80)],
            ['code' => 'CUT-P115', 'name' => 'Polar 115 Guillotine Cutter', 'configuration' => $this->configuration('cutting', 500, 600, 'sheets_per_hour', 10, 5, 0.5, null, 0, 100, 100, 1150, 1150, null, null, 0, null, null, null, null)],
            ['code' => 'FOLD-SB26', 'name' => 'Stahl B26 Folding Machine', 'configuration' => $this->configuration('folding', 450, 4200, 'sheets_per_hour', 20, 40, 1, null, 0, 100, 150, 660, 1000, null, null, 0, 40, 350, null, null)],
            ['code' => 'BIND-MMSS', 'name' => 'Muller Martini Saddle Stitcher', 'configuration' => $this->configuration('stitching', 550, 1500, 'products_per_hour', 30, 20, 1, null, 0, null, null, null, null, null, null, 0, null, null, null, null)],
            ['code' => 'BIND-PPB', 'name' => 'Perfecta Perfect Binder', 'configuration' => $this->configuration('perfect_binding', 650, 450, 'products_per_hour', 35, 15, 1.5, null, 0, null, null, null, null, null, null, 0, null, null, null, null)],
            ['code' => 'DIE-BSP102', 'name' => 'Bobst SP 102-E Die Cutter', 'configuration' => $this->configuration('die_cutting', 750, 4000, 'sheets_per_hour', 45, 100, 1, null, 0, 350, 400, 1020, 720, 1000, 700, 10, 80, 2000, null, 4)],
            ['code' => 'LAM-GBC165', 'name' => 'Laminator GBC Titan 165', 'configuration' => $this->configuration('laminating', 400, 90, 'm2_per_hour', 15, 2, 1, null, 0, 100, 100, 1575, 2000, 1575, 2000, 0, null, null, null, null)],
            ['code' => 'PROOF-EST54', 'name' => 'Epson SureColor SC-T5400', 'configuration' => $this->configuration('printing', 250, 100, 'sheets_per_hour', 10, 2, 1, 4, 0, 100, 100, 914, 2000, 900, 2000, 0, 60, 300, 0.05, 0.30)],
        ];
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private function configuration(
        string $operation,
        int $hourlyCost,
        int $speed,
        string $speedUnit,
        int $setupMinutes,
        int $setupWaste,
        float $runWastePercent,
        ?int $colorUnits,
        int $perfectingColorsPerSide,
        ?int $minSheetWidth,
        ?int $minSheetHeight,
        ?int $maxSheetWidth,
        ?int $maxSheetHeight,
        ?int $maxPrintWidth,
        ?int $maxPrintHeight,
        int $sideMargin,
        ?int $minGsm,
        ?int $maxGsm,
        ?float $minThickness,
        ?float $maxThickness,
    ): array {
        return [
            'operation_type' => $operation,
            'hourly_cost' => $hourlyCost,
            'production_speed' => $speed,
            'speed_unit' => $speedUnit,
            'setup_time_minutes' => $setupMinutes,
            'setup_waste' => $setupWaste,
            'run_waste_percent' => $runWastePercent,
            'minimum_run_minutes' => 0,
            'color_units' => $colorUnits,
            'perfecting_colors_per_side' => $perfectingColorsPerSide,
            'min_sheet_width_mm' => $minSheetWidth,
            'min_sheet_height_mm' => $minSheetHeight,
            'max_sheet_width_mm' => $maxSheetWidth,
            'max_sheet_height_mm' => $maxSheetHeight,
            'max_print_width_mm' => $maxPrintWidth,
            'max_print_height_mm' => $maxPrintHeight,
            'gripper_margin_mm' => $operation === 'printing' ? 15 : 0,
            'side_margin_mm' => $sideMargin,
            'trailing_margin_mm' => $operation === 'printing' ? 5 : 0,
            'min_gsm' => $minGsm,
            'max_gsm' => $maxGsm,
            'min_thickness_mm' => $minThickness,
            'max_thickness_mm' => $maxThickness,
            'is_active' => true,
        ];
    }
}

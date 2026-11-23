<?php

use App\Models\Machine;
use Database\Seeders\MachineCostingDemoSeeder;
use Database\Seeders\MachineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('configures the catalog machines and preserves manual costing values on reruns', function (): void {
    $this->seed(MachineSeeder::class);

    Machine::query()->where('code', 'PRESS-SM52')->update([
        'hourly_cost' => 1234,
        'production_speed' => 6400,
        'speed_unit' => 'sheets_per_hour',
        'operation_type' => 'printing',
        'color_units' => 4,
        'costing_configured' => true,
    ]);

    $this->seed(MachineCostingDemoSeeder::class);

    $configuredMachines = Machine::query()->where('costing_configured', true)->get();
    expect($configuredMachines)->toHaveCount(10)
        ->and((float) Machine::query()->where('code', 'PRESS-SM52')->value('hourly_cost'))->toBe(1234.0)
        ->and(Machine::query()->where('code', 'PRESS-SM74')->value('operation_type'))->toBe('printing')
        ->and(Machine::query()->where('code', 'DIE-BSP102')->value('speed_unit'))->toBe('sheets_per_hour')
        ->and(Machine::query()->where('code', 'LAM-GBC165')->value('speed_unit'))->toBe('m2_per_hour');

    Machine::query()->where('code', 'BIND-PPB')->update(['hourly_cost' => 777]);
    $this->seed(MachineCostingDemoSeeder::class);

    expect(Machine::query()->count())->toBe(10)
        ->and((float) Machine::query()->where('code', 'BIND-PPB')->value('hourly_cost'))->toBe(777.0);
});

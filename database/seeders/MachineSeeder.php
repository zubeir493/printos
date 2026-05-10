<?php

namespace Database\Seeders;

use App\Models\Machine;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    public function run(): void
    {
        $machines = [
            [
                'name' => 'Heidelberg SM 52',
                'code' => 'PRESS-SM52',
                'baseline_rounds_per_week' => 120,
            ],
            [
                'name' => 'Heidelberg SM 74',
                'code' => 'PRESS-SM74',
                'baseline_rounds_per_week' => 100,
            ],
            [
                'name' => 'Komori Lithrone S29',
                'code' => 'PRESS-KLS29',
                'baseline_rounds_per_week' => 110,
            ],
            [
                'name' => 'Polar 115 Guillotine Cutter',
                'code' => 'CUT-P115',
                'baseline_rounds_per_week' => 200,
            ],
            [
                'name' => 'Stahl B26 Folding Machine',
                'code' => 'FOLD-SB26',
                'baseline_rounds_per_week' => 180,
            ],
            [
                'name' => 'Muller Martini Saddle Stitcher',
                'code' => 'BIND-MMSS',
                'baseline_rounds_per_week' => 90,
            ],
            [
                'name' => 'Perfecta Perfect Binder',
                'code' => 'BIND-PPB',
                'baseline_rounds_per_week' => 80,
            ],
            [
                'name' => 'Bobst SP 102-E Die Cutter',
                'code' => 'DIE-BSP102',
                'baseline_rounds_per_week' => 95,
            ],
            [
                'name' => 'Laminator GBC Titan 165',
                'code' => 'LAM-GBC165',
                'baseline_rounds_per_week' => 150,
            ],
            [
                'name' => 'Epson SureColor SC-T5400',
                'code' => 'PROOF-EST54',
                'baseline_rounds_per_week' => 300,
            ],
        ];

        foreach ($machines as $machine) {
            Machine::updateOrCreate(
                ['code' => $machine['code']],
                $machine
            );
        }
    }
}

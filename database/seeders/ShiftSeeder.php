<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                ['1:30–10:30', '07:30:00', '16:30:00'],
                ['2:30–11:30', '08:30:00', '17:30:00'],
                ['1:30–8 Security', '07:30:00', '14:00:00'],
                ['8–4 Security', '14:00:00', '22:00:00'],
                ['4–6 Night Security', '22:00:00', '00:00:00'],
                ['6–1:30 Night Security', '00:00:00', '07:30:00'],
                ['12–8', '06:00:00', '14:00:00'],
                ['8–4', '14:00:00', '22:00:00'],
                ['12:30–9:30 Cleaning', '06:30:00', '15:30:00'],
                ['1–7', '07:00:00', '13:00:00'],
                ['1–9', '19:00:00', '03:00:00'],
                ['1–9 Night', '19:00:00', '03:00:00'],
            ] as [$name, $start, $end]) {
                Shift::firstOrCreate(['name' => $name], [
                    'start_time' => $start,
                    'end_time' => $end,
                    'break_start' => null,
                    'break_end' => null,
                    'break_minutes' => 0,
                    'break_paid' => false,
                    'grace_minutes' => 0,
                ]);
            }
        });
    }
}

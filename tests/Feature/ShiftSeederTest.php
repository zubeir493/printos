<?php

use App\Models\Shift;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds all twelve supplied shift names and device clock times without duplicates', function (): void {
    $this->seed([ShiftSeeder::class, ShiftSeeder::class]);

    expect(Shift::count())->toBe(12);
    foreach ([
        ['1:30–10:30', '07:30:00', '16:30:00', 540],
        ['2:30–11:30', '08:30:00', '17:30:00', 540],
        ['1:30–8 Security', '07:30:00', '14:00:00', 390],
        ['8–4 Security', '14:00:00', '22:00:00', 480],
        ['4–6 Night Security', '22:00:00', '00:00:00', 120],
        ['6–1:30 Night Security', '00:00:00', '07:30:00', 450],
        ['12–8', '06:00:00', '14:00:00', 480],
        ['8–4', '14:00:00', '22:00:00', 480],
        ['12:30–9:30 Cleaning', '06:30:00', '15:30:00', 540],
        ['1–7', '07:00:00', '13:00:00', 360],
        ['1–9', '19:00:00', '03:00:00', 480],
        ['1–9 Night', '19:00:00', '03:00:00', 480],
    ] as [$name, $start, $end, $minutes]) {
        $shift = Shift::where('name', $name)->firstOrFail();
        expect($shift->start_time)->toBe($start)->and($shift->end_time)->toBe($end)
            ->and($shift->expected_minutes)->toBe($minutes)
            ->and($shift->break_start)->toBeNull()->and($shift->break_end)->toBeNull()
            ->and($shift->break_minutes)->toBe(0)
            ->and($shift->is_night_shift)->toBe($end < $start);
    }
});

it('preserves manual shift edits when the seeder is run again', function (): void {
    $this->seed(ShiftSeeder::class);
    $shift = Shift::where('name', '1:30–10:30')->firstOrFail();
    $shift->update(['grace_minutes' => 5]);
    $this->seed(ShiftSeeder::class);
    expect($shift->fresh()->grace_minutes)->toBe(5)->and(Shift::count())->toBe(12);
});

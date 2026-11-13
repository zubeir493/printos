<?php

namespace App\Services\Hr;

use App\Models\WorkSchedule;
use Illuminate\Support\Facades\DB;

class ResolveAssignmentPattern
{
    /** @param array<int|string, array{day_of_week: int|string, shift_ids: array<int, int|string>}> $days */
    public function handle(array $days, ?int $existingId = null): WorkSchedule
    {
        app(ValidateShiftPeriods::class)->forPattern($days, 'days');
        $normalized = collect($days)->mapWithKeys(fn (array $day): array => [(int) $day['day_of_week'] => collect($day['shift_ids'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values()->all()])->sortKeys()->all();
        $existing = $existingId ? WorkSchedule::with('days.shift', 'days.additionalShifts')->find($existingId) : null;
        if ($existing && $existing->days->mapWithKeys(fn ($day): array => [$day->day_of_week => collect($day->shift_ids)->sort()->values()->all()])->sortKeys()->all() === $normalized) {
            return $existing;
        }
        $name = 'Week '.substr(hash('sha256', json_encode($normalized)), 0, 16);

        return DB::transaction(function () use ($name, $normalized): WorkSchedule {
            $schedule = WorkSchedule::firstOrCreate(['name' => $name]);
            if (! $schedule->days()->exists()) {
                foreach ($normalized as $weekday => $shiftIds) {
                    $schedule->days()->create(['day_of_week' => $weekday, 'shift_ids' => $shiftIds]);
                }
            }

            return $schedule;
        });
    }
}

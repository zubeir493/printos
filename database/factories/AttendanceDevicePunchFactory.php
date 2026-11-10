<?php

namespace Database\Factories;

use App\Models\AttendanceDevicePunch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceDevicePunch>
 */
class AttendanceDevicePunchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => 'office-mb360',
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'device_user_id' => '12',
            'punched_at' => '2026-11-09 08:00:00',
            'direction' => 0,
            'raw_data' => ['user_id' => '12', 'timestamp' => '2026-11-09 08:00:00', 'raw_direction' => 0],
        ];
    }
}

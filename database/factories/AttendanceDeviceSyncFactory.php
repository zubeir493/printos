<?php

namespace Database\Factories;

use App\Models\AttendanceDeviceSync;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceDeviceSync>
 */
class AttendanceDeviceSyncFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => fake()->unique()->uuid(),
            'status' => 'never_synced',
        ];
    }
}

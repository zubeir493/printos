<?php

namespace Database\Factories;

use App\Models\Dieline;
use App\Models\User;
use App\Services\Dielines\DielineGeometryService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dieline>
 */
class DielineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dimensions = [
            'l' => 160,
            'w' => 50,
            'h' => 90,
            'tuck_flap' => 28,
            'glue_flap' => 18,
            'dust_flap' => 25,
            'bleed' => 3,
            'board_thickness' => 1.5,
        ];

        return [
            'name' => 'FEFCO 0210 test dieline',
            'template_key' => 'fefco-0210',
            'created_by' => User::factory(),
            'dimensions' => $dimensions,
            'geometry' => app(DielineGeometryService::class)->generate('fefco-0210', $dimensions),
        ];
    }
}

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
            'tuck_flap' => 15,
            'tuck_radius' => 6,
            'dust_flap' => 100,
            'glue_flap' => 20,
        ];

        return [
            'name' => 'Reverse tuck flap box test dieline',
            'template_key' => 'reverse-tuck-flap-box',
            'created_by' => User::factory(),
            'dimensions' => $dimensions,
            'geometry' => app(DielineGeometryService::class)->generate('reverse-tuck-flap-box', $dimensions),
        ];
    }
}

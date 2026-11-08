<?php

namespace Database\Factories;

use App\Models\Dieline;
use App\Models\DielineImposition;
use App\Models\User;
use App\Services\Dielines\Templates\AutoBottomTuckTopTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DielineImposition>
 */
class DielineImpositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dieline_id' => Dieline::factory(),
            'user_id' => User::factory(),
            'name' => 'Auto Bottom Carton',
            'paper_size' => 'a1',
            'paper_width' => 594,
            'paper_height' => 841,
            'margin' => 0,
            'gap' => 0,
            'status' => 'queued',
            'source_geometry' => (new AutoBottomTuckTopTemplate)->generate((new AutoBottomTuckTopTemplate)->defaults()),
        ];
    }
}

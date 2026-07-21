<?php

namespace Database\Factories;

use App\Models\DielineTemplate;
use App\Services\Dielines\Templates\Fefco0210Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DielineTemplate>
 */
class DielineTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'standard' => 'FEFCO',
            'service_class' => Fefco0210Template::class,
            'active' => true,
            'defaults' => [
                'l' => 160,
                'w' => 50,
                'h' => 90,
            ],
        ];
    }
}

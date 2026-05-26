<?php

namespace Database\Factories;

use App\Models\Proforma;
use App\Models\ProformaTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProformaTask>
 */
class ProformaTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 1000);
        $unitPrice = fake()->randomFloat(2, 10, 100);

        return [
            'proforma_id' => Proforma::factory(),
            'name' => fake()->words(3, true),
            'quantity' => $quantity,
            'size' => fake()->randomElement(['A4', 'A5', 'Custom']),
            'unit_price' => $unitPrice,
            'task_cost' => round($quantity * $unitPrice, 2),
            'paper' => [],
            'deliverables' => [],
            'instructions' => fake()->sentence(),
        ];
    }
}

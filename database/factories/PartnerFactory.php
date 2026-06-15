<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->text(),
            'tin_number' => fake()->word(),
            'is_supplier' => fake()->boolean(),
            'is_customer' => fake()->boolean(),
        ];
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_customer' => true,
        ]);
    }

    public function supplier(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_supplier' => true,
        ]);
    }
}

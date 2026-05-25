<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'date' => fake()->date(),
            'reference' => fake()->word(),
            'source_type' => fake()->word(),
            'source_id' => fake()->randomNumber(),
            'narration' => fake()->text(),
            'total_debit' => fake()->randomFloat(2, 0, 9999999999999.99),
            'total_credit' => fake()->randomFloat(2, 0, 9999999999999.99),
            'status' => fake()->randomElement(['draft', 'posted', 'void']),
            'posted_at' => fake()->dateTime(),
            'voided_at' => fake()->dateTime(),
        ];
    }
}

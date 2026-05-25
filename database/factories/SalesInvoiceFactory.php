<?php

namespace Database\Factories;

use App\Models\JobOrder;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'invoice_number' => fake()->word(),
            'partner_id' => Partner::factory(),
            'job_order_id' => JobOrder::factory(),
            'issue_date' => fake()->date(),
            'due_date' => fake()->date(),
            'subtotal' => fake()->randomFloat(2, 0, 9999999999999.99),
            'tax_amount' => fake()->randomFloat(2, 0, 9999999999999.99),
            'total_amount' => fake()->randomFloat(2, 0, 9999999999999.99),
            'status' => fake()->randomElement(['draft', 'issued', 'partially_paid', 'paid', 'void']),
        ];
    }
}

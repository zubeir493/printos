<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'invoice_number' => fake()->word(),
            'partner_id' => Partner::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'issue_date' => fake()->date(),
            'due_date' => fake()->date(),
            'subtotal' => fake()->randomFloat(2, 0, 9999999999999.99),
            'tax_amount' => fake()->randomFloat(2, 0, 9999999999999.99),
            'total_amount' => fake()->randomFloat(2, 0, 9999999999999.99),
            'status' => fake()->randomElement(['draft', 'posted', 'partially_paid', 'paid', 'void']),
        ];
    }
}

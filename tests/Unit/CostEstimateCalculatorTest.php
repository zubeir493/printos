<?php

use App\Models\Setting;
use App\Services\CostEstimates\CostEstimateCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('calculates task totals and vat for estimator tasks', function (): void {
    Setting::createDefault();

    $result = app(CostEstimateCalculator::class)->calculate('books', [
        ['name' => 'Cover', 'quantity' => 2, 'unit_price' => 100],
        ['name' => 'Text', 'quantity' => 3, 'unit_price' => 50],
    ]);

    expect($result['subtotal'])->toBe(350.0)
        ->and($result['tax_amount'])->toBe(52.5)
        ->and($result['total'])->toBe(402.5)
        ->and($result['tasks'][0]['task_cost'])->toBe(200.0)
        ->and($result['tasks'][1]['task_cost'])->toBe(150.0);
});

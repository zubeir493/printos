<?php

use Database\Seeders\AccountSeeder;
use Database\Seeders\BankSeeder;
use Database\Seeders\DemoPortfolioSeeder;
use Database\Seeders\InventoryItemSeeder;
use Database\Seeders\MachineSeeder;
use Database\Seeders\PartnerSeeder;
use Database\Seeders\PayrollTaxRuleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('demo portfolio seeder creates connected data and can be rerun', function (): void {
    $this->seed([
        AccountSeeder::class,
        BankSeeder::class,
        WarehouseSeeder::class,
        InventoryItemSeeder::class,
        MachineSeeder::class,
        PartnerSeeder::class,
        UserSeeder::class,
        PayrollTaxRuleSeeder::class,
        DemoPortfolioSeeder::class,
        DemoPortfolioSeeder::class,
    ]);

    expect(DB::table('job_orders')->where('job_order_number', 'like', 'JO-PORT-%')->count())->toBe(3);
    expect(DB::table('job_order_tasks')->where('name', 'like', '%booklet%')->exists())->toBeTrue();
    expect(DB::table('production_plan_items')->count())->toBeGreaterThanOrEqual(3);
    expect(DB::table('inventory_balances')->where('quantity_on_hand', '>', 0)->count())->toBeGreaterThanOrEqual(6);
    expect(DB::table('payments')->where('payment_number', 'like', 'PAY-%-PORT-%')->count())->toBe(2);
    expect(DB::table('invoices')->where('invoice_number', 'like', '%PORT-%')->count())->toBe(3);
    expect(DB::table('bids')->where('bid_number', 'BID-PORT-1001')->exists())->toBeTrue();
    expect(DB::table('employees')->where('employee_id', 'like', 'EMP-100%')->count())->toBe(4);
});

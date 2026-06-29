<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            AccountSeeder::class,
            BankSeeder::class,
            WarehouseSeeder::class,
            InventoryItemSeeder::class,
            MachineSeeder::class,
            PartnerSeeder::class,
            UserSeeder::class,
            PayrollTaxRuleSeeder::class,
            AttendanceDemoSeeder::class,
            DemoPortfolioSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            [
                'name' => 'Main Warehouse',
                'code' => 'MAIN',
                'location' => 'Head Office',
                'is_default' => true,
            ],
            [
                'name' => 'Branch Warehouse',
                'code' => 'BR01',
                'location' => 'Branch Office 1',
                'is_default' => false,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                $warehouse
            );
        }
    }
}

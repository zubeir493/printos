<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'A4 80gsm Bond Paper',
                'sku' => 'PAPER-A4-80',
                'unit' => 'sheet',
                'purchase_unit' => 'ream',
                'conversion_factor' => 500,
                'type' => 'raw_material',
                'is_sellable' => false,
                'price' => 0.05,
            ],
            [
                'name' => 'A4 120gsm Glossy Paper',
                'sku' => 'PAPER-A4-120G',
                'unit' => 'sheet',
                'purchase_unit' => 'ream',
                'conversion_factor' => 500,
                'type' => 'raw_material',
                'is_sellable' => false,
                'price' => 0.12,
            ],
            [
                'name' => 'A5 80gsm Bond Paper',
                'sku' => 'PAPER-A5-80',
                'unit' => 'sheet',
                'purchase_unit' => 'ream',
                'conversion_factor' => 500,
                'type' => 'raw_material',
                'is_sellable' => false,
                'price' => 0.04,
            ],
            [
                'name' => 'Sticker Sheet A4',
                'sku' => 'STICKER-A4',
                'unit' => 'sheet',
                'purchase_unit' => 'pack',
                'conversion_factor' => 25,
                'type' => 'raw_material',
                'is_sellable' => false,
                'price' => 1.20,
            ],
            [
                'name' => 'Black Ink Cartridge',
                'sku' => 'INK-BLK',
                'unit' => 'piece',
                'purchase_unit' => 'piece',
                'conversion_factor' => 1,
                'type' => 'raw_material',
                'is_sellable' => false,
                'price' => 25.00,
            ],
            [
                'name' => 'Duplex 70*100cm',
                'sku' => 'DUPLEX-70X100',
                'unit' => 'piece',
                'purchase_unit' => 'ream',
                'conversion_factor' => 500,
                'type' => 'finished_good',
                'is_sellable' => true,
                'price' => 3500.00,
            ],
        ];

        foreach ($items as $item) {
            InventoryItem::updateOrCreate(
                ['sku' => $item['sku']],
                $item
            );
        }
    }
}

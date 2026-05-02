<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            ['size' => 'A3'],
            ['size' => 'A4'],
            ['size' => 'A5'],
            ['size' => 'A6'],
            ['size' => 'DL'],
            ['size' => 'Business Card'],
            ['size' => 'Letter'],
            ['size' => 'Legal'],
        ];

        foreach ($sizes as $size) {
            Size::updateOrCreate($size, $size);
        }
    }
}

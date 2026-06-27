<?php

use App\Filament\Resources\CostEstimates\Schemas\BookCostingWizardSchema;
use App\Services\Costing\BookCostCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('costing_service_types')->updateOrInsert(
            ['key' => 'books'],
            [
                'name' => 'Book Printing',
                'calculator_class' => BookCostCalculator::class,
                'schema_class' => BookCostingWizardSchema::class,
                'active' => true,
                'defaults' => json_encode([
                    'overhead_percent' => 15,
                    'profit_margin_percent' => 25,
                    'book_text_paper_unit_cost' => round(7000 / 1.15, 2),
                    'book_cover_plate_unit_cost' => 900,
                    'book_ink_unit_cost' => 3000,
                    'book_wire_unit_cost' => 160,
                    'book_hotmelt_glue_unit_cost' => 10000,
                    'book_packing_material_unit_cost' => 30,
                    'book_printing_speed' => 2500,
                    'book_printing_rate' => 200,
                    'book_folding_speed' => 2500,
                    'book_folding_rate' => 175,
                    'book_collating_speed' => 2500,
                    'book_collating_rate' => 0.05,
                    'book_cutting_rate' => 100,
                    'book_laminating_speed' => 300,
                    'book_laminating_rate' => 80,
                    'book_perfect_binding_speed' => 700,
                    'book_perfect_binding_rate' => 140,
                    'book_gluing_speed' => 25,
                    'book_gluing_rate' => 60,
                    'book_packing_multiplier' => 12,
                    'book_packing_rate' => 30,
                    'book_typesetting_rate' => 20,
                    'book_artwork_hours' => 3,
                    'book_artwork_rate' => 600,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('costing_service_types')->where('key', 'books')->delete();
    }
};

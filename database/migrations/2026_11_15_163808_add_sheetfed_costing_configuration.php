<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            foreach ([
                'thickness' => fn () => $table->decimal('thickness', 10, 4)->nullable(),
                'grain_direction' => fn () => $table->string('grain_direction')->nullable(),
                'printable_sides' => fn () => $table->unsignedTinyInteger('printable_sides')->nullable(),
                'density_kg_per_litre' => fn () => $table->decimal('density_kg_per_litre', 10, 4)->nullable(),
                'consumption_grams_per_m2' => fn () => $table->decimal('consumption_grams_per_m2', 10, 4)->nullable(),
                'is_self_adhesive' => fn () => $table->boolean('is_self_adhesive')->default(false),
            ] as $column => $definition) {
                if (! Schema::hasColumn('inventory_items', $column)) {
                    $definition();
                }
            }
            $table->decimal('average_cost', 18, 6)->default(0)->change();
        });

        Schema::table('machines', function (Blueprint $table): void {
            foreach ([
                'setup_time_minutes' => fn () => $table->decimal('setup_time_minutes', 10, 2)->default(0),
                'setup_waste' => fn () => $table->decimal('setup_waste', 12, 2)->default(0),
                'speed_unit' => fn () => $table->string('speed_unit')->nullable(),
                'costing_configured' => fn () => $table->boolean('costing_configured')->default(false),
                'is_active' => fn () => $table->boolean('is_active')->default(true),
                'color_units' => fn () => $table->unsignedTinyInteger('color_units')->nullable(),
                'perfecting_colors_per_side' => fn () => $table->unsignedTinyInteger('perfecting_colors_per_side')->default(0),
                'min_sheet_width_mm' => fn () => $table->decimal('min_sheet_width_mm', 10, 2)->nullable(),
                'min_sheet_height_mm' => fn () => $table->decimal('min_sheet_height_mm', 10, 2)->nullable(),
                'max_sheet_width_mm' => fn () => $table->decimal('max_sheet_width_mm', 10, 2)->nullable(),
                'max_sheet_height_mm' => fn () => $table->decimal('max_sheet_height_mm', 10, 2)->nullable(),
                'max_print_width_mm' => fn () => $table->decimal('max_print_width_mm', 10, 2)->nullable(),
                'max_print_height_mm' => fn () => $table->decimal('max_print_height_mm', 10, 2)->nullable(),
                'gripper_margin_mm' => fn () => $table->decimal('gripper_margin_mm', 8, 2)->default(0),
                'side_margin_mm' => fn () => $table->decimal('side_margin_mm', 8, 2)->default(0),
                'trailing_margin_mm' => fn () => $table->decimal('trailing_margin_mm', 8, 2)->default(0),
                'min_gsm' => fn () => $table->decimal('min_gsm', 8, 2)->nullable(),
                'max_gsm' => fn () => $table->decimal('max_gsm', 8, 2)->nullable(),
                'min_thickness_mm' => fn () => $table->decimal('min_thickness_mm', 10, 4)->nullable(),
                'max_thickness_mm' => fn () => $table->decimal('max_thickness_mm', 10, 4)->nullable(),
                'run_waste_percent' => fn () => $table->decimal('run_waste_percent', 5, 2)->default(0),
                'minimum_run_minutes' => fn () => $table->decimal('minimum_run_minutes', 10, 2)->default(0),
            ] as $column => $definition) {
                if (! Schema::hasColumn('machines', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('cost_estimates', function (Blueprint $table): void {
            $table->json('calculation_snapshot')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->decimal('unit_price', 18, 6)->default(0)->change();
        });
        Schema::table('cost_estimate_lines', function (Blueprint $table): void {
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 6)->default(0)->change();
            $table->decimal('unit_cost', 18, 6)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_estimate_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('machine_id');
        });
        Schema::table('cost_estimates', fn (Blueprint $table) => $table->dropColumn(['calculation_snapshot', 'currency_code']));
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropColumn(['density_kg_per_litre', 'consumption_grams_per_m2', 'is_self_adhesive']));
        Schema::table('machines', fn (Blueprint $table) => $table->dropColumn([
            'costing_configured', 'is_active', 'color_units', 'perfecting_colors_per_side',
            'min_sheet_width_mm', 'min_sheet_height_mm', 'max_sheet_width_mm', 'max_sheet_height_mm',
            'max_print_width_mm', 'max_print_height_mm', 'gripper_margin_mm', 'side_margin_mm', 'trailing_margin_mm',
            'min_gsm', 'max_gsm', 'min_thickness_mm', 'max_thickness_mm', 'run_waste_percent', 'minimum_run_minutes',
        ]));
    }
};

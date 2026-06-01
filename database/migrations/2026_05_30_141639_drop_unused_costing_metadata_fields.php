<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('inventory_items', 'thickness') ? 'thickness' : null,
                Schema::hasColumn('inventory_items', 'weight') ? 'weight' : null,
                Schema::hasColumn('inventory_items', 'grain_direction') ? 'grain_direction' : null,
                Schema::hasColumn('inventory_items', 'printable_sides') ? 'printable_sides' : null,
                Schema::hasColumn('inventory_items', 'adhesive_type') ? 'adhesive_type' : null,
                Schema::hasColumn('inventory_items', 'liner_type') ? 'liner_type' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('machines', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('machines', 'setup_time_minutes') ? 'setup_time_minutes' : null,
                Schema::hasColumn('machines', 'setup_waste') ? 'setup_waste' : null,
                Schema::hasColumn('machines', 'setup_cost') ? 'setup_cost' : null,
                Schema::hasColumn('machines', 'make_ready_cost') ? 'make_ready_cost' : null,
                Schema::hasColumn('machines', 'default_waste_quantity') ? 'default_waste_quantity' : null,
                Schema::hasColumn('machines', 'speed_unit') ? 'speed_unit' : null,
                Schema::hasColumn('machines', 'electricity_cost') ? 'electricity_cost' : null,
                Schema::hasColumn('machines', 'supported_materials') ? 'supported_materials' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            foreach ([
                'thickness' => fn () => $table->decimal('thickness', 8, 3)->nullable(),
                'weight' => fn () => $table->decimal('weight', 10, 3)->nullable(),
                'grain_direction' => fn () => $table->string('grain_direction')->nullable(),
                'printable_sides' => fn () => $table->unsignedTinyInteger('printable_sides')->nullable(),
                'adhesive_type' => fn () => $table->string('adhesive_type')->nullable(),
                'liner_type' => fn () => $table->string('liner_type')->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('inventory_items', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('machines', function (Blueprint $table): void {
            foreach ([
                'setup_time_minutes' => fn () => $table->unsignedInteger('setup_time_minutes')->default(0),
                'setup_waste' => fn () => $table->decimal('setup_waste', 12, 2)->default(0),
                'setup_cost' => fn () => $table->decimal('setup_cost', 12, 2)->default(0),
                'make_ready_cost' => fn () => $table->decimal('make_ready_cost', 12, 2)->default(0),
                'default_waste_quantity' => fn () => $table->decimal('default_waste_quantity', 12, 2)->default(0),
                'speed_unit' => fn () => $table->string('speed_unit')->nullable(),
                'electricity_cost' => fn () => $table->decimal('electricity_cost', 12, 2)->default(0),
                'supported_materials' => fn () => $table->json('supported_materials')->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('machines', $column)) {
                    $definition();
                }
            }
        });
    }
};

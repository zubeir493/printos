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
        Schema::table('machines', function (Blueprint $table): void {
            foreach ([
                'operation_type' => fn () => $table->string('operation_type')->nullable()->after('baseline_rounds_per_week')->index(),
                'speed_unit' => fn () => $table->string('speed_unit')->nullable()->after('production_speed'),
                'setup_cost' => fn () => $table->decimal('setup_cost', 12, 2)->default(0)->after('setup_waste'),
                'make_ready_cost' => fn () => $table->decimal('make_ready_cost', 12, 2)->default(0)->after('setup_cost'),
                'default_waste_quantity' => fn () => $table->decimal('default_waste_quantity', 12, 2)->default(0)->after('make_ready_cost'),
            ] as $column => $definition) {
                if (! Schema::hasColumn('machines', $column)) {
                    $definition();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table): void {
            if (Schema::hasColumn('machines', 'operation_type')) {
                $table->dropIndex(['operation_type']);
            }

            foreach (['operation_type', 'speed_unit', 'setup_cost', 'make_ready_cost', 'default_waste_quantity'] as $column) {
                if (Schema::hasColumn('machines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

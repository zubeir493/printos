<?php

use App\Filament\Resources\CostEstimates\Schemas\LabelCostingWizardSchema;
use App\Filament\Resources\CostEstimates\Schemas\PackageCostingWizardSchema;
use App\Services\Costing\LabelCostCalculator;
use App\Services\Costing\PackageCostCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cost_estimates')) {
            Schema::create('cost_estimates', function (Blueprint $table): void {
                $table->id();
                $table->string('estimate_number')->unique();
                $table->string('job_type')->default('labels');
                $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
                $table->json('services')->nullable();
                $table->text('remarks')->nullable();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('status')->default('draft');
                $table->timestamps();
            });
        }

        Schema::table('cost_estimates', function (Blueprint $table): void {
            if (! Schema::hasColumn('cost_estimates', 'description')) {
                $table->string('description')->nullable()->after('partner_id');
            }

            if (! Schema::hasColumn('cost_estimates', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1)->after('description');
            }

            if (! Schema::hasColumn('cost_estimates', 'deadline')) {
                $table->date('deadline')->nullable()->after('quantity');
            }

            if (! Schema::hasColumn('cost_estimates', 'overhead_amount')) {
                $table->decimal('overhead_amount', 12, 2)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('cost_estimates', 'profit_amount')) {
                $table->decimal('profit_amount', 12, 2)->default(0)->after('overhead_amount');
            }

            if (! Schema::hasColumn('cost_estimates', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('profit_amount');
            }

            if (! Schema::hasColumn('cost_estimates', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(0)->after('discount_amount');
            }

            if (! Schema::hasColumn('cost_estimates', 'unit_price')) {
                $table->decimal('unit_price', 12, 4)->default(0)->after('total');
            }

            if (! Schema::hasColumn('cost_estimates', 'margin_percent')) {
                $table->decimal('margin_percent', 6, 2)->default(0)->after('unit_price');
            }

            if (! Schema::hasColumn('cost_estimates', 'formula_version')) {
                $table->string('formula_version')->default('v1')->after('margin_percent');
            }

            if (! Schema::hasColumn('cost_estimates', 'settings_snapshot')) {
                $table->json('settings_snapshot')->nullable()->after('formula_version');
            }

            if (! Schema::hasColumn('cost_estimates', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('settings_snapshot');
            }
        });

        if (! Schema::hasTable('cost_estimate_inputs')) {
            Schema::create('cost_estimate_inputs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cost_estimate_id')->constrained()->cascadeOnDelete();
                $table->string('step');
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->unique(['cost_estimate_id', 'step']);
            });
        }

        if (! Schema::hasTable('cost_estimate_lines')) {
            Schema::create('cost_estimate_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cost_estimate_id')->constrained()->cascadeOnDelete();
                $table->string('category');
                $table->string('label');
                $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('quantity', 15, 4)->default(0);
                $table->string('unit')->nullable();
                $table->decimal('unit_cost', 15, 4)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->json('snapshot')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('costing_service_types')) {
            Schema::create('costing_service_types', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('calculator_class');
                $table->string('schema_class');
                $table->boolean('active')->default(true);
                $table->json('defaults')->nullable();
                $table->timestamps();
            });
        }

        DB::table('costing_service_types')->updateOrInsert(
            ['key' => 'labels'],
            [
                'name' => 'Label Printing',
                'calculator_class' => LabelCostCalculator::class,
                'schema_class' => LabelCostingWizardSchema::class,
                'active' => true,
                'defaults' => json_encode(['waste_percent' => 3, 'profit_margin_percent' => 20]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('costing_service_types')->updateOrInsert(
            ['key' => 'packages'],
            [
                'name' => 'Packaging / Folding Carton',
                'calculator_class' => PackageCostCalculator::class,
                'schema_class' => PackageCostingWizardSchema::class,
                'active' => true,
                'defaults' => json_encode(['waste_percent' => 3, 'profit_margin_percent' => 20]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        Schema::table('inventory_items', function (Blueprint $table): void {
            foreach (
                [
                    'gsm' => fn () => $table->decimal('gsm', 8, 2)->nullable(),
                    'thickness' => fn () => $table->decimal('thickness', 8, 3)->nullable(),
                    'width' => fn () => $table->decimal('width', 10, 3)->nullable(),
                    'height' => fn () => $table->decimal('height', 10, 3)->nullable(),
                    'weight' => fn () => $table->decimal('weight', 10, 3)->nullable(),
                    'grain_direction' => fn () => $table->string('grain_direction')->nullable(),
                    'printable_sides' => fn () => $table->unsignedTinyInteger('printable_sides')->nullable(),
                    'default_waste_percent' => fn () => $table->decimal('default_waste_percent', 5, 2)->nullable(),
                    'adhesive_type' => fn () => $table->string('adhesive_type')->nullable(),
                    'liner_type' => fn () => $table->string('liner_type')->nullable(),
                ] as $column => $definition
            ) {
                if (! Schema::hasColumn('inventory_items', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('machines', function (Blueprint $table): void {
            foreach (
                [
                    'setup_time_minutes' => fn () => $table->unsignedInteger('setup_time_minutes')->default(0),
                    'setup_waste' => fn () => $table->decimal('setup_waste', 12, 2)->default(0),
                    'hourly_cost' => fn () => $table->decimal('hourly_cost', 12, 2)->default(0),
                    'production_speed' => fn () => $table->decimal('production_speed', 12, 2)->default(0),
                    'electricity_cost' => fn () => $table->decimal('electricity_cost', 12, 2)->default(0),
                    'supported_materials' => fn () => $table->json('supported_materials')->nullable(),
                ] as $column => $definition
            ) {
                if (! Schema::hasColumn('machines', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('settings', 'costing_defaults')) {
                $table->json('costing_defaults')->nullable();
            }
        });

        Schema::table('proformas', function (Blueprint $table): void {
            if (! Schema::hasColumn('proformas', 'cost_estimate_id')) {
                $table->foreignId('cost_estimate_id')->nullable()->after('proforma_number')->constrained()->nullOnDelete();
            }
        });

        Schema::table('proforma_tasks', function (Blueprint $table): void {
            foreach (
                [
                    'inputs' => fn () => $table->json('inputs')->nullable(),
                    'cost_breakdown' => fn () => $table->json('cost_breakdown')->nullable(),
                    'rate_snapshot' => fn () => $table->json('rate_snapshot')->nullable(),
                ] as $column => $definition
            ) {
                if (! Schema::hasColumn('proforma_tasks', $column)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_service_types');
        Schema::dropIfExists('cost_estimate_lines');
        Schema::dropIfExists('cost_estimate_inputs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_orders', 'cost_calc_file')) {
                $table->string('cost_calc_file')->default('legacy-cost-calculation')->after('job_type');
            }

            if (Schema::hasColumn('job_orders', 'proforma_id')) {
                $table->dropForeign(['proforma_id']);
                $table->dropUnique('job_orders_proforma_id_unique');
                $table->dropColumn('proforma_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_orders', 'proforma_id')) {
                $table->foreignId('proforma_id')
                    ->nullable()
                    ->after('id')
                    ->unique()
                    ->constrained()
                    ->restrictOnDelete();
            }

            if (Schema::hasColumn('job_orders', 'cost_calc_file')) {
                $table->dropColumn('cost_calc_file');
            }
        });
    }
};

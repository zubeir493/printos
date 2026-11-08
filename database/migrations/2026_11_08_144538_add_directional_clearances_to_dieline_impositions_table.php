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
        Schema::table('dieline_impositions', function (Blueprint $table) {
            $table->decimal('horizontal_gap', 6, 2)->nullable();
            $table->decimal('vertical_gap', 6, 2)->nullable();
            $table->decimal('minimum_parallel_clearance', 6, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dieline_impositions', function (Blueprint $table) {
            $table->dropColumn(['horizontal_gap', 'vertical_gap', 'minimum_parallel_clearance']);
        });
    }
};

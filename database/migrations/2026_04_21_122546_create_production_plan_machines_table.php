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
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('production_plan_machines');
        Schema::create('production_plan_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained();
            $table->timestamps();
        });

        // Recreate production_plan_items to avoid foreign key issues when dropping and recreating tables
        Schema::dropIfExists('production_plan_items');

        Schema::create('production_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_order_task_id')->constrained();
            $table->decimal('planned_quantity', 15, 2);
            $table->integer('planned_plates')->default(0);
            $table->integer('planned_rounds')->default(0);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('production_plan_items');
        Schema::dropIfExists('production_plan_machines');

        // Recreate original production_plan_items
        Schema::create('production_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained();
            $table->foreignId('job_order_task_id')->constrained();
            $table->decimal('planned_quantity', 15, 2);
            $table->integer('planned_plates')->default(0);
            $table->integer('planned_rounds')->default(0);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }
};

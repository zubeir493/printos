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
        Schema::create('production_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_plan_item_id')->constrained();
            $table->date('date');
            $table->decimal('actual_quantity', 15, 2);
            $table->integer('plates_used')->default(0);
            $table->integer('rounds')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_report_items');
    }
};

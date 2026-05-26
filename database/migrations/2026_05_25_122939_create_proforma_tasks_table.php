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
        Schema::create('proforma_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('size')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('task_cost', 12, 2)->default(0);
            $table->json('paper')->nullable();
            $table->json('deliverables')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proforma_tasks');
    }
};

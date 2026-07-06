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
        Schema::create('overtime_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('minutes_basis')->index();
            $table->json('applies_on_days')->nullable();
            $table->decimal('multiplier', 8, 4)->default(1);
            $table->decimal('hourly_rate', 15, 4)->nullable();
            $table->unsignedSmallInteger('minimum_minutes')->default(0);
            $table->unsignedSmallInteger('rounding_increment_minutes')->default(1);
            $table->time('window_start_time')->nullable();
            $table->time('window_end_time')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_rules');
    }
};

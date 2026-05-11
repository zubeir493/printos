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

        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table->string('job_order_number')->unique();
            $table->foreignId('partner_id')->constrained();
            $table->enum('job_type', ['books', 'packages']);
            $table->string('cost_calc_file');
            $table->json('services');
            $table->date('submission_date');
            $table->text('remarks')->nullable();
            $table->decimal('advance_amount', 12, 2);
            $table->boolean('advance_paid')->default(false);
            $table->decimal('total_price', 12, 2);
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
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
        Schema::dropIfExists('job_orders');
        Schema::enableForeignKeyConstraints();
    }
};

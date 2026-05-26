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
        Schema::create('cost_estimates', function (Blueprint $table) {
            $table->id();
            $table->string('estimate_number')->unique();
            $table->string('job_type');
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_estimates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->string('bid_number')->unique();
            $table->string('title');
            $table->string('tender_reference')->nullable();
            $table->foreignId('partner_id')->constrained();
            $table->string('status')->default('draft');
            $table->date('submission_date')->nullable();
            $table->date('deadline_date')->nullable();
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deadline_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};

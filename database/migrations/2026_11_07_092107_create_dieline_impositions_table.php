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
        Schema::create('dieline_impositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dieline_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('paper_size');
            $table->unsignedSmallInteger('paper_width');
            $table->unsignedSmallInteger('paper_height');
            $table->decimal('margin', 6, 2);
            $table->decimal('gap', 6, 2);
            $table->string('status')->default('queued');
            $table->json('source_geometry');
            $table->json('placements')->nullable();
            $table->unsignedInteger('copies')->nullable();
            $table->decimal('utilisation', 5, 2)->nullable();
            $table->string('disk')->nullable();
            $table->json('files')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'dieline_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dieline_impositions');
    }
};

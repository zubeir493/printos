<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dielines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('template_key')->index();
            $table->foreignId('job_order_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('dimensions');
            $table->json('geometry');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dielines');
    }
};

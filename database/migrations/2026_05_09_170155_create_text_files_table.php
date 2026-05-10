<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_files');
    }
};

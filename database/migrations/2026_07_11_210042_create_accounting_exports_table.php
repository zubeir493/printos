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
        Schema::create('accounting_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cutoff_at');
            $table->string('status')->default('processing')->index();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('journal_count')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();

            $table->index(['accounting_integration_id', 'cutoff_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_exports');
    }
};

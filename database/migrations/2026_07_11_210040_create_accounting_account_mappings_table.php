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
        Schema::create('accounting_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('external_account_id');
            $table->timestamps();

            $table->unique(['accounting_integration_id', 'account_id'], 'accounting_mapping_integration_account_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_account_mappings');
    }
};

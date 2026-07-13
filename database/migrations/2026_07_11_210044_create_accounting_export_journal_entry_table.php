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
        Schema::create('accounting_export_journal_entry', function (Blueprint $table) {
            $table->foreignId('accounting_integration_id')
                ->constrained('accounting_integrations')
                ->cascadeOnDelete()
                ->name('aej_ai_fk');

            $table->foreignId('accounting_export_id')
                ->constrained('accounting_exports')
                ->cascadeOnDelete()
                ->name('aej_ae_fk');

            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')
                ->restrictOnDelete()
                ->name('aej_je_fk');

            $table->timestamps();

            $table->primary(['accounting_export_id', 'journal_entry_id']);
            $table->unique(['accounting_integration_id', 'journal_entry_id'], 'accounting_export_integration_journal_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_export_journal_entry');
    }
};

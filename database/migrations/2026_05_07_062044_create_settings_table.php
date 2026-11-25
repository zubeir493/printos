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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // Company Information
            $table->string('company_name')->default('PrintOS');
            $table->text('company_address')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_website')->nullable();
            $table->string('company_tax_id')->nullable();
            $table->string('company_logo')->nullable();
            // VAT/Tax Settings
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->boolean('vat_enabled')->default(true);
            $table->boolean('workers_union_enabled')->default(true);
            $table->decimal('workers_union_rate', 5, 2)->default(1);
            $table->json('tax_configuration')->nullable();
            // Invoice Settings
            $table->text('invoice_terms')->nullable();
            $table->integer('invoice_due_days')->default(30);
            $table->string('invoice_prefix')->default('INV');
            $table->string('receipt_prefix')->default('RCP');
            // Currency Settings
            $table->string('currency_code')->default('ETB');
            $table->string('currency_symbol')->default('Birr');
            // Email Settings
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->text('email_footer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

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
        Schema::create('employee_loan_installment_payment', function (Blueprint $table) {
            $table->foreignId('employee_loan_installment_id')->constrained(indexName: 'loan_installment_payment_installment_fk')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->primary(['employee_loan_installment_id', 'payment_id'], 'loan_installment_payment_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_loan_installment_payment');
    }
};

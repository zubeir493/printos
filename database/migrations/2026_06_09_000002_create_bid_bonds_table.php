<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_bonds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issuing_partner_id')->constrained('partners');
            $table->foreignId('bank_id')->nullable()->constrained();
            $table->decimal('amount', 15, 2);
            $table->date('issue_date')->nullable();
            $table->date('recovery_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('issue_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('recovery_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_bonds');
    }
};

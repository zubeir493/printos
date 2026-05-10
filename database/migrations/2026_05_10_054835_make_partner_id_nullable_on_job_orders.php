<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->foreignId('partner_id')
                ->nullable()
                ->change();
            $table->foreign('partner_id')
                ->references('id')
                ->on('partners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->foreignId('partner_id')
                ->nullable(false)
                ->change();
            $table->foreign('partner_id')
                ->references('id')
                ->on('partners')
                ->restrictOnDelete();
        });
    }
};

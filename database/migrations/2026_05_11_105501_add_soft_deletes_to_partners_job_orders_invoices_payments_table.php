<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::statement('DROP VIEW IF EXISTS bank_transactions');

            Schema::table('partners', function (Blueprint $table): void {
                $table->softDeletes();
            });

            Schema::table('job_orders', function (Blueprint $table): void {
                $table->softDeletes();
            });

            Schema::table('invoices', function (Blueprint $table): void {
                $table->softDeletes();
            });

            Schema::table('payments', function (Blueprint $table): void {
                $table->softDeletes();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('partners', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });

            Schema::table('job_orders', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });

            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });

            Schema::table('payments', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};

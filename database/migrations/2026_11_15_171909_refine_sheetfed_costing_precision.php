<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_tasks', function (Blueprint $table): void {
            $table->decimal('unit_price', 18, 6)->default(0)->change();
        });
        Schema::table('machines', function (Blueprint $table): void {
            $table->decimal('setup_time_minutes', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('proforma_tasks', function (Blueprint $table): void {
            $table->decimal('unit_price', 12, 2)->default(0)->change();
        });
    }
};

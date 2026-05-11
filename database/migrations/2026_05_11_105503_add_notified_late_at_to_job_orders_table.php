<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table): void {
            $table->timestamp('notified_late_at')->nullable()->after('production_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table): void {
            $table->dropColumn('notified_late_at');
        });
    }
};

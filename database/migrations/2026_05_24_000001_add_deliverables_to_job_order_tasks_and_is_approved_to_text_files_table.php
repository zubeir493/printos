<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_tasks', function (Blueprint $table): void {
            $table->json('deliverables')->nullable()->after('instructions');
        });

        Schema::table('text_files', function (Blueprint $table): void {
            $table->boolean('is_approved')->default(false)->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('job_order_tasks', function (Blueprint $table): void {
            $table->dropColumn('deliverables');
        });

        Schema::table('text_files', function (Blueprint $table): void {
            $table->dropColumn('is_approved');
        });
    }
};

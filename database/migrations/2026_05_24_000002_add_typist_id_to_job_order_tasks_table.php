<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_tasks', function (Blueprint $table): void {
            $table->foreignId('typist_id')
                ->nullable()
                ->after('designer_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_order_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('typist_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('text_files') || ! Schema::hasColumn('text_files', 'job_order_id')) {
            return;
        }

        Schema::table('text_files', function (Blueprint $table) {
            $table->dropForeign(['job_order_id']);
            $table->renameColumn('job_order_id', 'job_order_task_id');
            $table->foreign('job_order_task_id')->references('id')->on('job_order_tasks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('text_files') || ! Schema::hasColumn('text_files', 'job_order_task_id')) {
            return;
        }

        Schema::table('text_files', function (Blueprint $table) {
            $table->dropForeign(['job_order_task_id']);
            $table->renameColumn('job_order_task_id', 'job_order_id');
            $table->foreign('job_order_id')->references('id')->on('job_orders')->cascadeOnDelete();
        });
    }
};

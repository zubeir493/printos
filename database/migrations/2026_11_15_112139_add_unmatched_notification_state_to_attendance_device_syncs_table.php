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
        Schema::table('attendance_device_syncs', function (Blueprint $table) {
            $table->json('notified_unmatched_ids')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_device_syncs', function (Blueprint $table) {
            $table->dropColumn('notified_unmatched_ids');
        });
    }
};

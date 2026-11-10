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
        Schema::create('attendance_device_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->string('status')->default('never_synced');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_download_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->timestamps();
        });

        Schema::create('attendance_device_punches', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->string('fingerprint', 64)->unique();
            $table->string('device_user_id')->index();
            $table->timestamp('punched_at')->index();
            $table->unsignedSmallInteger('direction');
            $table->json('raw_data');
            $table->foreignId('attendance_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_segment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('attendance_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
        });

        Schema::table('attendance_segments', function (Blueprint $table) {
            $table->string('device_sync_key', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_device_punches');
        Schema::dropIfExists('attendance_device_syncs');
        Schema::table('attendance_segments', function (Blueprint $table) {
            $table->dropUnique(['device_sync_key']);
            $table->dropColumn('device_sync_key');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_shift_id');
        });
    }
};

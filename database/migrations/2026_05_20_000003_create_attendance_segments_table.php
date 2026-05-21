<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_segments')) {
            return;
        }

        Schema::create('attendance_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_import_row_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('fp_no');
            $table->string('schedule_name')->nullable();
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->decimal('day_fraction', 5, 2)->default(0);
            $table->string('status')->nullable();
            $table->string('exception')->nullable();
            $table->text('correction_reason')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_segments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_day_shifts', function (Blueprint $table): void {
            $table->foreignId('work_schedule_day_id')->constrained(indexName: 'schedule_day_shifts_day_fk')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['work_schedule_day_id', 'shift_id'], 'schedule_day_shifts_primary');
        });
        Schema::create('employee_shift_override_shifts', function (Blueprint $table): void {
            $table->foreignId('employee_shift_override_id')->constrained(indexName: 'override_shifts_override_fk')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['employee_shift_override_id', 'shift_id'], 'override_shifts_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shift_override_shifts');
        Schema::dropIfExists('work_schedule_day_shifts');
    }
};

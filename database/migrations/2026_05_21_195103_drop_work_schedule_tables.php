<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->createShiftsTableIfMissing();

        Schema::table('attendance_daily_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_daily_summaries', 'work_schedule_id')) {
                $table->dropConstrainedForeignId('work_schedule_id');
            }

            if (Schema::hasColumn('attendance_daily_summaries', 'shift_id')) {
                $table->dropConstrainedForeignId('shift_id');
            }
        });

        Schema::dropIfExists('employee_schedule_assignments');
        Schema::dropIfExists('work_schedule_days');
        Schema::dropIfExists('work_schedules');

        Schema::table('attendance_segments', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_segments', 'shift_id')) {
                $table->foreignId('shift_id')->nullable()->after('employee_id');
            }
        });

        $this->backfillShiftsFromAttendanceSegments();

        if (! $this->foreignKeyExists('attendance_segments', 'attendance_segments_shift_id_foreign')) {
            Schema::table('attendance_segments', function (Blueprint $table) {
                $table->foreign('shift_id')
                    ->references('id')
                    ->on('shifts')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_segments', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_segments', 'shift_id')) {
                $table->dropConstrainedForeignId('shift_id');
            }
        });

        if (! Schema::hasTable('work_schedules')) {
            Schema::create('work_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('work_schedule_days')) {
            Schema::create('work_schedule_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('day_of_week');
                $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('is_working_day')->default(true);
                $table->timestamps();

                $table->unique(['work_schedule_id', 'day_of_week']);
            });
        }

        if (! Schema::hasTable('employee_schedule_assignments')) {
            Schema::create('employee_schedule_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
                $table->date('effective_from');
                $table->date('effective_until')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'effective_from', 'effective_until'], 'employee_schedule_date_index');
            });
        }

        Schema::table('attendance_daily_summaries', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_daily_summaries', 'work_schedule_id')) {
                $table->foreignId('work_schedule_id')->nullable()->after('date')->constrained()->nullOnDelete();
            }
        });

        Schema::table('attendance_daily_summaries', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_daily_summaries', 'shift_id')) {
                $table->foreignId('shift_id')->nullable()->after('work_schedule_id')->constrained()->nullOnDelete();
            }
        });
    }

    private function createShiftsTableIfMissing(): void
    {
        if (Schema::hasTable('shifts')) {
            return;
        }

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('expected_minutes')->default(480);
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('overtime_eligible')->default(true);
            $table->timestamps();
        });
    }

    private function backfillShiftsFromAttendanceSegments(): void
    {
        if (! Schema::hasColumn('attendance_segments', 'shift_id')) {
            return;
        }

        DB::table('attendance_segments')
            ->select('schedule_name', 'scheduled_start', 'scheduled_end')
            ->whereNotNull('schedule_name')
            ->where('schedule_name', '!=', '')
            ->orderBy('id')
            ->get()
            ->unique('schedule_name')
            ->each(function (object $segment): void {
                $shift = DB::table('shifts')
                    ->where('name', $segment->schedule_name)
                    ->first();

                $values = [
                    'start_time' => $segment->scheduled_start ?? '00:00:00',
                    'end_time' => $segment->scheduled_end ?? '00:00:00',
                    'expected_minutes' => $this->expectedMinutes($segment->scheduled_start, $segment->scheduled_end),
                    'updated_at' => now(),
                ];

                if (! $shift) {
                    $shiftId = DB::table('shifts')->insertGetId([
                        'name' => $segment->schedule_name,
                        'break_minutes' => 0,
                        'grace_minutes' => 0,
                        'is_night_shift' => false,
                        'overtime_eligible' => true,
                        'created_at' => now(),
                        ...$values,
                    ]);
                } else {
                    $shiftId = $shift->id;

                    DB::table('shifts')
                        ->where('id', $shiftId)
                        ->update($values);
                }

                DB::table('attendance_segments')
                    ->where('schedule_name', $segment->schedule_name)
                    ->update(['shift_id' => $shiftId]);
            });
    }

    private function expectedMinutes(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 480;
        }

        $startTime = Carbon::createFromFormat('H:i:s', $start);
        $endTime = Carbon::createFromFormat('H:i:s', $end);

        if ($endTime->lt($startTime)) {
            $endTime = $endTime->addDay();
        }

        return max(1, (int) $startTime->diffInMinutes($endTime));
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return true;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('overtime_types') && ! Schema::hasTable('overtime_rules')) {
            Schema::rename('overtime_types', 'overtime_rules');
        }

        if (Schema::hasTable('overtime_rules')) {
            if (Schema::hasColumn('overtime_rules', 'source_rule') && ! Schema::hasColumn('overtime_rules', 'minutes_basis')) {
                Schema::table('overtime_rules', function (Blueprint $table): void {
                    $table->renameColumn('source_rule', 'minutes_basis');
                });
            }

            if (! Schema::hasColumn('overtime_rules', 'applies_on_days')) {
                Schema::table('overtime_rules', function (Blueprint $table): void {
                    $table->json('applies_on_days')->nullable()->after('minutes_basis');
                });
            }

            if (Schema::hasColumn('overtime_rules', 'night_start_time') && ! Schema::hasColumn('overtime_rules', 'window_start_time')) {
                Schema::table('overtime_rules', function (Blueprint $table): void {
                    $table->renameColumn('night_start_time', 'window_start_time');
                });
            }

            if (Schema::hasColumn('overtime_rules', 'night_end_time') && ! Schema::hasColumn('overtime_rules', 'window_end_time')) {
                Schema::table('overtime_rules', function (Blueprint $table): void {
                    $table->renameColumn('night_end_time', 'window_end_time');
                });
            }

            $this->mapExistingRules();
        }

        if (Schema::hasTable('payroll_overtime_entries') && Schema::hasColumn('payroll_overtime_entries', 'overtime_type_id') && ! Schema::hasColumn('payroll_overtime_entries', 'overtime_rule_id')) {
            Schema::table('payroll_overtime_entries', function (Blueprint $table): void {
                $table->renameColumn('overtime_type_id', 'overtime_rule_id');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('payroll_overtime_entries') && Schema::hasColumn('payroll_overtime_entries', 'overtime_rule_id') && ! Schema::hasColumn('payroll_overtime_entries', 'overtime_type_id')) {
            Schema::table('payroll_overtime_entries', function (Blueprint $table): void {
                $table->renameColumn('overtime_rule_id', 'overtime_type_id');
            });
        }

        if (Schema::hasTable('overtime_rules')) {
            if (Schema::hasColumn('overtime_rules', 'window_start_time') && ! Schema::hasColumn('overtime_rules', 'night_start_time')) {
                Schema::table('overtime_rules', function (Blueprint $table): void {
                    $table->renameColumn('window_start_time', 'night_start_time');
                });
            }

            if (Schema::hasColumn('overtime_rules', 'window_end_time') && ! Schema::hasColumn('overtime_rules', 'night_end_time')) {
                Schema::table('overtime_rules', function (Blueprint $table): void {
                    $table->renameColumn('window_end_time', 'night_end_time');
                });
            }

            if (Schema::hasColumn('overtime_rules', 'minutes_basis') && ! Schema::hasColumn('overtime_rules', 'source_rule')) {
                Schema::table('overtime_rules', function (Blueprint $table): void {
                    $table->renameColumn('minutes_basis', 'source_rule');
                });
            }

            if (Schema::hasColumn('overtime_rules', 'applies_on_days')) {
                Schema::table('overtime_rules', function (Blueprint $table): void {
                    $table->dropColumn('applies_on_days');
                });
            }
        }

        if (Schema::hasTable('overtime_rules') && ! Schema::hasTable('overtime_types')) {
            Schema::rename('overtime_rules', 'overtime_types');
        }

        Schema::enableForeignKeyConstraints();
    }

    private function mapExistingRules(): void
    {
        foreach ([
            'normal_overtime' => ['attendance_overtime', ['regular']],
            'before_shift' => ['before_shift', ['regular']],
            'after_shift' => ['after_shift', ['regular']],
            'night_window' => ['time_window', ['regular']],
            'weekend_dayoff' => ['worked_day', ['weekend_dayoff']],
            'holiday' => ['worked_day', ['holiday']],
            'manual' => ['manual', ['regular', 'weekend_dayoff', 'holiday']],
        ] as $oldBasis => [$newBasis, $days]) {
            DB::table('overtime_rules')
                ->where('minutes_basis', $oldBasis)
                ->update([
                    'minutes_basis' => $newBasis,
                    'applies_on_days' => json_encode($days),
                ]);
        }
    }
};

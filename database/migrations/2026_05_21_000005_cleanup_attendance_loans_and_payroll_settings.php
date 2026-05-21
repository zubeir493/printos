<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'workers_union_enabled')) {
                $table->boolean('workers_union_enabled')->default(true)->after('vat_enabled');
            }

            if (! Schema::hasColumn('settings', 'workers_union_rate')) {
                $table->decimal('workers_union_rate', 5, 2)->default(1)->after('workers_union_enabled');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'union_enabled')) {
                $table->dropColumn('union_enabled');
            }

            if (Schema::hasColumn('employees', 'union_rate')) {
                $table->dropColumn('union_rate');
            }
        });

        if (! Schema::hasTable('employee_loans')) {
            Schema::create('employee_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->date('loan_date');
                $table->date('return_date');
                $table->decimal('amount', 15, 2);
                $table->text('reason')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();

                $table->index(['employee_id', 'return_date', 'status']);
            });
        }

        Schema::dropIfExists('employee_recurring_deductions');

        Schema::table('attendance_segments', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_segments', 'shift_type')) {
                $table->dropColumn('shift_type');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loans');
    }
};

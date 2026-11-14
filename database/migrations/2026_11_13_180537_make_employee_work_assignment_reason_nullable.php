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
        Schema::table('employee_work_assignments', function (Blueprint $table): void {
            $table->text('reason')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('employee_work_assignments')->whereNull('reason')->update(['reason' => '']);
        Schema::table('employee_work_assignments', function (Blueprint $table): void {
            $table->text('reason')->nullable(false)->change();
        });
    }
};

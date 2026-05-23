<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'transport_allowance')) {
                $table->decimal('transport_allowance', 15, 2)->default(0)->after('basic_salary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'transport_allowance')) {
                $table->dropColumn('transport_allowance');
            }
        });
    }
};

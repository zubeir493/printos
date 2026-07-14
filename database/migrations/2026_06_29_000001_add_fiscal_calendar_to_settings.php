<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'fiscal_calendar')) {
                $table->string('fiscal_calendar')->default('gregorian')->after('currency_symbol');
            }
        });

        DB::table('settings')->update([
            'currency_code' => 'ETB',
            'currency_symbol' => 'Birr',
            'fiscal_calendar' => 'gregorian',
        ]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'fiscal_calendar')) {
                $table->dropColumn('fiscal_calendar');
            }
        });
    }
};

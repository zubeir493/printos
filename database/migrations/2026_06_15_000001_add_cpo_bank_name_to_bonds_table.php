<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonds', function (Blueprint $table): void {
            if (! Schema::hasColumn('bonds', 'cpo_bank_name')) {
                $table->string('cpo_bank_name')->nullable()->after('bank_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bonds', function (Blueprint $table): void {
            if (Schema::hasColumn('bonds', 'cpo_bank_name')) {
                $table->dropColumn('cpo_bank_name');
            }
        });
    }
};

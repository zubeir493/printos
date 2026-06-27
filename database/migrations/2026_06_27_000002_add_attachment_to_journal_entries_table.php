<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('journal_entries', 'attachment')) {
                $table->string('attachment')->nullable()->after('source_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('journal_entries', 'attachment')) {
                $table->dropColumn('attachment');
            }
        });
    }
};

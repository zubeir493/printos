<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->string('deliverable')->nullable()->after('filename');
        });

        Schema::table('text_files', function (Blueprint $table): void {
            $table->string('deliverable')->nullable()->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->dropColumn('deliverable');
        });

        Schema::table('text_files', function (Blueprint $table): void {
            $table->dropColumn('deliverable');
        });
    }
};

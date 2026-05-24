<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role')->default('sales')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'design', 'typist', 'production', 'finance', 'sales', 'warehouse', 'hr', 'retail', 'operations') NOT NULL DEFAULT 'sales'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->enum('role', ['admin', 'design', 'production', 'finance', 'sales', 'warehouse', 'hr', 'retail', 'operations'])->default('sales')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'design', 'production', 'finance', 'sales', 'warehouse', 'hr', 'retail', 'operations') NOT NULL DEFAULT 'sales'");
    }
};

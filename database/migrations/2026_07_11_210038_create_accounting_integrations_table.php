<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounting_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('name');
            $table->boolean('enabled')->default(false);
            $table->string('timezone')->default('Africa/Addis_Ababa');
            $table->time('daily_cutoff')->default('23:55');
            $table->json('configuration')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_integrations');
    }
};

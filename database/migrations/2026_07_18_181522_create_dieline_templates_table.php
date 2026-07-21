<?php

use App\Services\Dielines\Templates\Fefco0210Template;
use App\Services\Dielines\Templates\Fefco0427Template;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dieline_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('standard')->default('FEFCO');
            $table->string('service_class');
            $table->boolean('active')->default(true);
            $table->json('defaults')->nullable();
            $table->timestamps();
        });

        DB::table('dieline_templates')->insert([
            [
                'key' => 'fefco-0210',
                'name' => 'FEFCO 0210',
                'standard' => 'FEFCO',
                'service_class' => Fefco0210Template::class,
                'active' => true,
                'defaults' => json_encode([
                    'l' => 160,
                    'w' => 50,
                    'h' => 90,
                    'tuck_flap' => 28,
                    'glue_flap' => 18,
                    'dust_flap' => 25,
                    'bleed' => 3,
                    'board_thickness' => 1.5,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'fefco-0427',
                'name' => 'FEFCO 0427',
                'standard' => 'FEFCO',
                'service_class' => Fefco0427Template::class,
                'active' => true,
                'defaults' => json_encode([
                    'l' => 220,
                    'w' => 160,
                    'h' => 45,
                    'lid_tuck' => 35,
                    'side_lock' => 28,
                    'front_lock' => 24,
                    'bleed' => 3,
                    'board_thickness' => 1.5,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dieline_templates');
    }
};

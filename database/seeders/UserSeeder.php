<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'root',
            'email' => 'root@filament.site',
            'password' => Hash::make('root123'),
            'role' => 'admin',
        ]);

        DB::table('users')->insert([
            'name' => 'designer',
            'email' => 'designer@filament.site',
            'password' => Hash::make('designer123'),
            'role' => 'design',
        ]);

        DB::table('users')->insert([
            'name' => 'finance',
            'email' => 'finance@filament.site',
            'password' => Hash::make('finance123'),
            'role' => 'finance',
        ]);

        DB::table('users')->insert([
            'name' => 'warehouse',
            'email' => 'warehouse@filament.site',
            'password' => Hash::make('warehouse123'),
            'role' => 'warehouse',
        ]);

        DB::table('users')->insert([
            'name' => 'operations',
            'email' => 'operations@filament.site',
            'password' => Hash::make('operations123'),
            'role' => 'operations',
        ]);

        DB::table('users')->insert([
            'name' => 'hr',
            'email' => 'hr@filament.site',
            'password' => Hash::make('hr123'),
            'role' => 'hr',
        ]);


    }
}

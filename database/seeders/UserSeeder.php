<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insertOrIgnore([
            [
                'name' => 'root',
                'email' => 'root@filament.site',
                'password' => Hash::make('root123'),
                'role' => 'admin',
            ],
            [
                'name' => 'designer',
                'email' => 'designer@filament.site',
                'password' => Hash::make('designer123'),
                'role' => 'design',
            ],
            [
                'name' => 'finance',
                'email' => 'finance@filament.site',
                'password' => Hash::make('finance123'),
                'role' => 'finance',
            ],
            [
                'name' => 'warehouse',
                'email' => 'warehouse@filament.site',
                'password' => Hash::make('warehouse123'),
                'role' => 'warehouse',
            ],
            [
                'name' => 'operations',
                'email' => 'operations@filament.site',
                'password' => Hash::make('operations123'),
                'role' => 'operations',
            ],
            [
                'name' => 'hr',
                'email' => 'hr@filament.site',
                'password' => Hash::make('hr123'),
                'role' => 'hr',
            ],
            [
                'name' => 'production',
                'email' => 'production@filament.site',
                'password' => Hash::make('production123'),
                'role' => 'production',
            ],
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::count() === 0) {
            Setting::createDefault();
            $this->command->info('Default settings created successfully.');
        } else {
            $this->command->info('Settings already exist. Skipping.');
        }
    }
}

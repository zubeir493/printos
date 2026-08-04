<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dieline_templates')
            ->whereIn('key', ['fefco-0210', 'fefco-0427', 'rounded-tuck-carton'])
            ->delete();
    }

    public function down(): void {}
};

<?php

use App\Services\JobOrders\LegacyJobOrderGroupSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LegacyJobOrderGroupSchema::ensure();
    }

    public function down(): void
    {
        throw new RuntimeException('Legacy job-order group data is retained for historical billing and task references. Restore a database backup to remove it.');
    }
};

<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\MaintenanceStoreEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\MaintenanceStore;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MaintenanceStoreEntrySeeder extends Seeder
{
    public function run(MaintenanceStore $store, InventoryService $inventory): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $admin = User::query()->where('role', UserRole::Admin)->first();
        if (! $admin) {
            $this->command?->warn('Create a local administrator before loading maintenance store samples.');

            return;
        }

        $guard = Auth::guard('web');
        $previous = $guard->user();
        $guard->setUser($admin);

        try {
            DB::transaction(function () use ($store, $inventory): void {
                $warehouse = Warehouse::firstOrCreate(['code' => 'DEMO-MAINT'], ['name' => '[Demo] Maintenance Store']);
                if (MaintenanceStoreEntry::query()->where('warehouse_id', $warehouse->id)->exists()) {
                    return;
                }

                $employee = Employee::query()->where('employee_id', 'DEMO-MAINT')->firstOr(fn (): Employee => Employee::factory()->create([
                    'employee_id' => 'DEMO-MAINT', 'first_name' => 'Demo', 'last_name' => 'Technician',
                ]));
                $machine = Machine::firstOrCreate(['code' => 'DEMO-MAINT-PRESS'], ['name' => '[Demo] Press', 'operation_type' => 'printing']);
                $tool = InventoryItem::firstOrCreate(['sku' => 'DEMO-MAINT-WRENCH'], ['name' => '[Demo] Wrench', 'type' => 'tools', 'unit' => 'piece', 'is_sellable' => false]);
                $part = InventoryItem::firstOrCreate(['sku' => 'DEMO-MAINT-BELT'], ['name' => '[Demo] Drive belt', 'type' => 'spare_parts', 'unit' => 'piece', 'is_sellable' => false]);

                $inventory->createMovement($tool, $warehouse, 'adjustment', 5);
                $inventory->createMovement($part, $warehouse, 'adjustment', 5);
                $store->checkoutTool($tool->id, $warehouse->id, $employee->id, 2, today()->addWeek()->toDateString());
                $store->issueSparePart($part->id, $warehouse->id, $machine->id, 1, 'DEMO-REPAIR');
            });
        } finally {
            if ($previous) {
                $guard->setUser($previous);
            } else {
                $guard->forgetUser();
            }
        }
    }
}

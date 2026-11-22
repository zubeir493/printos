<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\MaintenanceStoreEntry;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceStoreEntry>
 */
class MaintenanceStoreEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory()->state(['type' => 'tools', 'unit' => 'piece', 'average_cost' => 0, 'is_sellable' => false]),
            'warehouse_id' => Warehouse::factory(),
            'employee_id' => Employee::factory(),
            'type' => MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT,
            'quantity' => 2,
            'returned_quantity' => 0,
        ];
    }

    public function sparePartIssue(Machine $machine): static
    {
        return $this->state([
            'inventory_item_id' => InventoryItem::factory()->state(['type' => 'spare_parts', 'unit' => 'piece', 'average_cost' => 0, 'is_sellable' => false]),
            'employee_id' => null,
            'machine_id' => $machine->id,
            'type' => MaintenanceStoreEntry::TYPE_SPARE_PART_ISSUE,
        ]);
    }
}

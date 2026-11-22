<?php

namespace App\Models;

use Database\Factories\MaintenanceStoreEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaintenanceStoreEntry extends Model
{
    /** @use HasFactory<MaintenanceStoreEntryFactory> */
    use HasFactory;

    use LogsActivity;

    public const string TYPE_TOOL_CHECKOUT = 'tool_checkout';

    public const string TYPE_SPARE_PART_ISSUE = 'spare_part_issue';

    public const string MOVEMENT_TOOL_RETURN = 'tool_return';

    protected $fillable = [
        'inventory_item_id', 'warehouse_id', 'employee_id', 'machine_id', 'recorded_by',
        'type', 'quantity', 'returned_quantity', 'due_date', 'reference', 'notes',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'returned_quantity' => 'decimal:2', 'due_date' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'inventory_item_id', 'warehouse_id', 'employee_id', 'machine_id', 'quantity', 'returned_quantity', 'due_date', 'reference'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('maintenance_store');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function outstandingQuantity(): float
    {
        return $this->type === self::TYPE_TOOL_CHECKOUT
            ? round((float) $this->quantity - (float) $this->returned_quantity, 2)
            : 0.0;
    }
}

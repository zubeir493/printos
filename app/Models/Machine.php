<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Machine extends Model
{
    use LogsActivity;

    public const array OPERATION_TYPES = [
        'printing' => 'Printing',
        'die_cutting' => 'Die Cutting',
        'folder_gluer' => 'Folder Gluer',
        'slitting' => 'Slitting',
        'rewinding' => 'Rewinding',
        'cutting' => 'Cutting',
        'packing' => 'Packing',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('production');
    }

    protected $fillable = [
        'name',
        'code',
        'baseline_rounds_per_week',
        'operation_type',
        'hourly_cost',
        'production_speed',
    ];

    protected function casts(): array
    {
        return [
            'baseline_rounds_per_week' => 'integer',
            'hourly_cost' => 'decimal:2',
            'production_speed' => 'decimal:2',
        ];
    }

    public function scopeOperation(Builder $query, string $operationType): Builder
    {
        return $query->where('operation_type', $operationType);
    }

    public function productionPlanItems(): HasMany
    {
        return $this->hasMany(ProductionPlanItem::class);
    }
}

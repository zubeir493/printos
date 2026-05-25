<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductionPlan extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('production');
    }

    protected $fillable = ['week_start', 'week_end', 'status'];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
        ];
    }

    public function machines(): HasMany
    {
        return $this->hasMany(ProductionPlanMachine::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionPlanItem::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductionReport::class);
    }
}
